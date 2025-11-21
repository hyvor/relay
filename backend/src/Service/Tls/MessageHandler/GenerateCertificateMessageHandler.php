<?php

namespace App\Service\Tls\MessageHandler;

use App\Entity\Type\DnsRecordType;
use App\Service\Dns\DnsRecordService;
use App\Service\Dns\Dto\CreateDnsRecordDto;
use App\Service\Tls\Acme\AcmeClient;
use App\Service\Tls\Acme\Exception\AcmeException;
use App\Service\Tls\Message\GenerateCertificateMessage;
use App\Service\Tls\TlsCertificateService;
use Hyvor\Internal\Bundle\Log\ContextualLogger;
use Hyvor\Internal\Util\Crypt\Encryption;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GenerateCertificateMessageHandler
{

    private LoggerInterface $logger;

    public function __construct(
        private AcmeClient $acmeClient,
        private TlsCertificateService $tlsCertificateService,
        private DnsRecordService $dnsRecordService,
        LoggerInterface $logger,
        private ClockInterface $clock,
    ) {
        $this->logger = ContextualLogger::forMessageHandler($logger, self::class);
    }

    public function __invoke(GenerateCertificateMessage $message): void
    {
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

        try {
            $logger->info('Starting ACME client and setting up an account');

            $this->acmeClient->setLogger($logger);
            $this->acmeClient->init();

            $logger->info('Account initialized. Creating new order');
            $order = $this->acmeClient->newOrder($cert->getDomain());

            $logger->info('Order created. Creating DNS challenge record');
            $dnsRecord = new CreateDnsRecordDto(
                DnsRecordType::TXT,
                '_acme-challenge',
                $order->dnsRecordValue,
                ttl: 30
            );
            $this->dnsRecordService->createDnsRecord($dnsRecord);

            $logger->info('DNS challenge record created. Waiting for DNS propagation (60 seconds)');
            $this->clock->sleep(60);

            $logger->info('Finalizing order with ACME server');
            $certPem = $this->acmeClient->finalizeOrder($order, $privateKey);
        } catch (AcmeException $e) {
            $logger->error('ACME error occurred during certificate generation', [
                'exception' => $e,
            ]);
            return;
        }
    }

}