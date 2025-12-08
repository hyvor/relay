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

#[AsMessageHandler]
class CheckCertificateVailidityMessageHandler
{
    private const RENEWAL_THRESHOLD_DAYS = 30;

    private LoggerInterface $logger;

    public function __construct(
        private TlsCertificateService $tlsCertificateService,
        private MailTlsGenerator $mailTlsGenerator,
        private ClockInterface $clock,
        LoggerInterface $streamerLogger,
    ) {
        $this->logger = ContextualLogger::forMessageHandler($streamerLogger, self::class);
    }

    public function __invoke(CheckCertificateVailidityMessage $message): void
    {
        $cert = $this->tlsCertificateService->getLatestCertificateByType(TlsCertificateType::MAIL);
    
        if ($cert === null) {
            $this->logger->info('No mail TLS certificate found, skipping validity check');
            return;
        }
        
        if ($cert->getStatus() !== TlsCertificateStatus::ACTIVE) {
            $this->logger->info('Mail TLS certificate is not active, skipping validity check', [
                'status' => $cert->getStatus()->value,
            ]);
            return;
        }
    
        $validTo = $cert->getValidTo();
        if ($validTo === null) {
            $this->logger->warning('Mail TLS certificate has no valid_to date, skipping validity check');
            return;
        }

        $now = $this->clock->now();
        $thresholdDate = $now->modify('+' . self::RENEWAL_THRESHOLD_DAYS . ' days');

        if ($validTo > $thresholdDate) {
     
            $this->logger->info('Mail TLS certificate is valid, no renewal needed', [
                'validTo' => $validTo->format('Y-m-d H:i:s'),
                'thresholdDate' => $thresholdDate->format('Y-m-d H:i:s'),
            ]);
            return;
        }
        
        $this->logger->info('Mail TLS certificate expires within threshold, starting renewal', [
            'validTo' => $validTo->format('Y-m-d H:i:s'),
            'thresholdDays' => self::RENEWAL_THRESHOLD_DAYS,
        ]);

        try {
            $this->mailTlsGenerator->dispatchToGenerate();
            $this->logger->info('Mail TLS certificate renewal dispatched');
        } catch (AnotherTlsGenerationRequestInProgressException) {
            $this->logger->info('Another TLS certificate generation is already in progress, skipping');
        }
    }
}
