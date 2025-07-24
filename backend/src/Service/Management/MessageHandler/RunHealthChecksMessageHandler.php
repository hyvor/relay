<?php

namespace App\Service\Management\MessageHandler;

use App\Service\Management\Health\HealthCheckService;
use App\Service\Management\Message\RunHealthChecksMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RunHealthChecksMessageHandler
{
    public function __construct(
        private HealthCheckService $healthCheckService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(RunHealthChecksMessage $message): void
    {
        try {
            $this->logger->info('Running health checks...');
            $this->healthCheckService->runAllHealthChecks();
            $this->logger->info('Health checks completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Health checks failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 