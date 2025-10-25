<?php

namespace App\Service\Management\MessageHandler;

use App\Entity\ServerTask;
use App\Entity\Type\ServerTaskType;
use App\Service\App\Process\ProcessFactory;
use App\Service\Go\GoHttpApi;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\Message\ServerTaskMessage;
use App\Service\Server\ServerService;
use App\Service\ServerTask\ServerTaskService;
use Hyvor\Internal\Bundle\Log\ContextualLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ServerTaskMessageHandler
{

    private LoggerInterface $logger;

    public function __construct(
        private ServerService $serverService,
        private ServerTaskService $serverTaskService,
        private GoHttpApi $goHttpApi,
        private GoStateFactory $goStateFactory,
        private ProcessFactory $processFactory,
        LoggerInterface $logger,
    ) {
        $this->logger = ContextualLogger::forMessageHandler($logger, self::class);
    }

    public function __invoke(ServerTaskMessage $message): void
    {
        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            $this->logger->warning(
                'Task received, but no server found for the current hostname. This could indicate that the server was deleted or not initialized properly.'
            );
            return;
        }

        $tasks = $this->serverTaskService->getTaskForServer($server);

        foreach ($tasks as $task) {
            // @phpstan-ignore-next-line
            if ($task->getType() === ServerTaskType::UPDATE_STATE) {
                $this->handleUpdateStateTask($task);
            }

            $this->serverTaskService->deleteTask($task);
        }
    }

    private function handleUpdateStateTask(ServerTask $task): void
    {
        $payload = $task->getPayload();

        if ($payload['api_workers_updated']) {
            $process = $this->processFactory->create([
                'supervisorctl',
                'restart',
                'frankenphp'
            ]);

            $this->logger->info('FrankenPHP restarting after API workers update');
            $process->run();
            $this->logger->info('FrankenPHP workers restarted: ' . $process->getOutput());

            $errorOutput = $process->getErrorOutput();
            if (!empty($errorOutput)) {
                // @codeCoverageIgnoreStart
                $this->logger->error('Error output from restarting FrankenPHP workers: ' . $errorOutput);
                // @codeCoverageIgnoreEnd
            }
        }

        $this->goHttpApi->updateState($this->goStateFactory->create());
    }
}
