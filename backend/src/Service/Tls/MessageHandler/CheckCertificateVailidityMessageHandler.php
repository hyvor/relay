<?php

namespace App\Service\Tls\MessageHandler;

use App\Entity\Type\TlsCertificateStatus;
use App\Entity\Type\TlsCertificateType;
use App\Service\Tls\Exception\AnotherTlsGenerationRequestInProgressException;
use App\Service\Tls\MailTlsGenerator;
use App\Service\Tls\Message\CheckCertificateVailidityMessage;
use App\Service\Tls\TlsCertificateService;
use Hyvor\Internal\Bundle\Log\ContextualLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Service\Instance\InstanceService;

#[AsMessageHandler]
class CheckCertificateVailidityMessageHandler
{
    private const RENEWAL_THRESHOLD_DAYS = 30;

    private LoggerInterface $logger;

    public function __construct(
        private TlsCertificateService $tlsCertificateService,
        private MailTlsGenerator $mailTlsGenerator,
        private ClockInterface $clock,
        private InstanceService $instanceService,
        LoggerInterface $streamerLogger,
    ) {
        $this->logger = ContextualLogger::forMessageHandler($streamerLogger, self::class);
    }

    public function __invoke(CheckCertificateVailidityMessage $message): void
    {
        dd('message received');
        $instance = $this->instanceService->getInstance();
        $cert = $this->tlsCertificateService->getInstanceMailTlsCertificate($instance);
        dd('instance received');
        if ($cert === null) {
            $this->logger->info('No mail TLS certificate found, skipping validity check');
            return;
        }
        dd('cert received');
        if ($cert->getStatus() !== TlsCertificateStatus::ACTIVE) {
            $this->logger->info('Mail TLS certificate is not active, skipping validity check', [
                'status' => $cert->getStatus()->value,
            ]);
            return;
        }
        dd('cert active');
        $validTo = $cert->getValidTo();
        if ($validTo === null) {
            $this->logger->warning('Mail TLS certificate has no valid_to date, skipping validity check');
            return;
        }
        dd('cert valid to');
        $now = $this->clock->now();
        $thresholdDate = $now->modify('+' . self::RENEWAL_THRESHOLD_DAYS . ' days');
        dd('threshold date');
        if ($validTo > $thresholdDate) {
     
            $this->logger->info('Mail TLS certificate is valid, no renewal needed', [
                'validTo' => $validTo->format('Y-m-d H:i:s'),
                'thresholdDate' => $thresholdDate->format('Y-m-d H:i:s'),
            ]);
            return;
        }
        dd('logging renewal');
        $this->logger->info('Mail TLS certificate expires within threshold, starting renewal', [
            'validTo' => $validTo->format('Y-m-d H:i:s'),
            'thresholdDays' => self::RENEWAL_THRESHOLD_DAYS,
        ]);

        dd('dispatching renewal');
        try {
            $this->mailTlsGenerator->dispatchToGenerate();
            $this->logger->info('Mail TLS certificate renewal dispatched');
        } catch (AnotherTlsGenerationRequestInProgressException) {
            $this->logger->info('Another TLS certificate generation is already in progress, skipping');
        }
    }
}
