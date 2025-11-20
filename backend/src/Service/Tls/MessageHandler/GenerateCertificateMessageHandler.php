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
use Psr\Log\LoggerInterface;
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

        try {
            $this->acmeClient->init();
            $order = $this->acmeClient->newOrder($cert->getDomain());

            $dnsRecord = new CreateDnsRecordDto(
                DnsRecordType::TXT,
                '_acme-challenge',
                $order->dnsRecordValue,
                ttl: 30
            );
            $this->dnsRecordService->createDnsRecord($dnsRecord);
            //

        } catch (AcmeException $e) {
        }
    }

}