<?php

namespace App\Service\Management\MessageHandler;

use App\Service\Management\Health\HealthCheckService;
use App\Service\Management\Message\RunHealthChecksMessage;
use Hyvor\Internal\Bundle\Log\ContextualLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RunHealthChecksMessageHandler
{

    private LoggerInterface $logger;

    public function __construct(
        private HealthCheckService $healthCheckService,
        LoggerInterface $logger
    ) {
        $this->logger = ContextualLogger::forMessageHandler($logger, self::class);
    }

    public function __invoke(RunHealthChecksMessage $message): void
    {
        try {
            $this->logger->info('Running health checks');
            $this->healthCheckService->runAllHealthChecks();
            $this->logger->info('Health checks completed successfully');
            // @codeCoverageIgnoreStart
        } catch (\Exception $e) {
            $this->logger->error('Health checks failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        // @codeCoverageIgnoreEnd
    }
} 