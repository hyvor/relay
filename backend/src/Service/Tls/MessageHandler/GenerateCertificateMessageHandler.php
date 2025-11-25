<?php

namespace App\Service\Tls\MessageHandler;

use App\Entity\DnsRecord;
use App\Entity\Type\DnsRecordType;
use App\Service\App\Config;
use App\Service\Dns\DnsRecordService;
use App\Service\Dns\Dto\CreateDnsRecordDto;
use App\Service\Tls\Acme\AcmeClient;
use App\Service\Tls\Acme\Exception\AcmeException;
use App\Service\Tls\Message\GenerateCertificateMessage;
use App\Service\Tls\TlsCertificateService;
use Hyvor\Internal\Bundle\Log\ContextualLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GenerateCertificateMessageHandler
{

    private LoggerInterface $logger;

    public function __construct(
        private AcmeClient $acmeClient,
        private TlsCertificateService $tlsCertificateService,
        private DnsRecordService $dnsRecordService,
        private Config $config,
        LoggerInterface $streamerLogger,
        private LockFactory $lockFactory,
        private ClockInterface $clock,
    ) {
        $this->logger = ContextualLogger::forMessageHandler($streamerLogger, self::class);
    }

    public function __invoke(GenerateCertificateMessage $message): void
    {
        $lock = $this->lockFactory->createLockFromKey(
            $message->getLockKey(),
            300,
            false
        );

        if (!$lock->acquire()) {
            $this->logger->error('Could not acquire lock for TLS certificate generation, aborting');
            return;
        }

        $tlsCertificateId = $message->getTlsCertificateId();
        $cert = $this->tlsCertificateService->getCertificateById($tlsCertificateId);

        if ($cert === null) {
            $this->logger->error('TLS Certificate not found, unable to continue', [
                'tlsCertificateId' => $tlsCertificateId,
            ]);
            return;
        }

        $privateKey = $this->tlsCertificateService->getDecryptedPrivateKey($cert);

        $logger = ContextualLogger::from($this->logger, [
            'tlsCertificateId' => $tlsCertificateId,
            'domain' => $cert->getDomain(),
        ]);

        /** @var ?DnsRecord $dnsRecord */
        $dnsRecord = null;

        try {
            $logger->info('Starting ACME client and setting up an account');

            $this->acmeClient->setLogger($logger);
            $this->acmeClient->init();

            $logger->info('Account initialized. Creating new order');
            $order = $this->acmeClient->newOrder($cert->getDomain());

            $acmeSubdomain = $this->getAcmeSubdomain($order->domain);
            $logger->info('Order created. Creating DNS challenge record', [
                'domain' => $order->domain,
                'acmeSubdomain' => $acmeSubdomain,
                'dnsRecordValue' => $order->dnsRecordValue,
            ]);

            $dnsRecord = $this->dnsRecordService->createDnsRecord(
                new CreateDnsRecordDto(
                    DnsRecordType::TXT,
                    $acmeSubdomain,
                    $order->dnsRecordValue,
                    ttl: 30
                )
            );

            $waitSeconds = 10;
            $logger->info("DNS challenge record created. Waiting for DNS propagation ($waitSeconds seconds)");
            $this->clock->sleep($waitSeconds);

            $logger->info('Finalizing order with ACME server');
            $finalCertificate = $this->acmeClient->finalizeOrder($order, $privateKey);

            $this->tlsCertificateService->activateCertificate(
                $cert,
                $finalCertificate->certificatePem,
                $finalCertificate->validFrom,
                $finalCertificate->validTo,
            );
        } catch (AcmeException $e) {
            $logger->error('ACME error occurred during certificate generation', [
                'exception' => $e->getMessage(),
            ]);
            return;
        } finally {
            if ($dnsRecord !== null) {
                $this->dnsRecordService->deleteDnsRecord($dnsRecord);
                $logger->info('DNS challenge record deleted', [
                    'dnsRecordId' => $dnsRecord->getId(),
                ]);
            }

            $lock->release();
        }
    }

    private function getAcmeSubdomain(string $fullDomain): string
    {
        $instanceDomain = $this->config->getInstanceDomain();
        $suffix = '.' . $instanceDomain;
        if (str_ends_with($fullDomain, $suffix)) {
            $subdomain = substr($fullDomain, 0, -strlen($suffix));
            return "_acme-challenge.$subdomain";
        } else {
            return '_acme-challenge'; // @codeCoverageIgnore
        }
    }

}