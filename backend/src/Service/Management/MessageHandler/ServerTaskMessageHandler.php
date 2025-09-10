<?php

namespace App\Service\Management\MessageHandler;

use App\Command\RunFrankenphpWorkerCommand;
use App\Entity\ServerTask;
use App\Entity\Type\ServerTaskType;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\Message\ServerTaskMessage;
use App\Service\Go\GoHttpApi;
use App\Service\Server\ServerService;
use App\Service\Server\ServerTaskService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
class ServerTaskMessageHandler
{

    public function __construct(
        private ServerService $serverService,
        private ServerTaskService $serverTaskService,
        private LoggerInterface $logger,
        private GoHttpApi $goHttpApi,
        private GoStateFactory $goStateFactory,
    )
    {
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
            if ($task->getType() == ServerTaskType::UPDATE_STATE)
                $this->handleUpdateStateTask($task);
            $this->serverTaskService->deleteTask($task);
        }
    }

    private function handleUpdateStateTask(ServerTask $task): void
    {
        $payload = $task->getPayload();

        if ($payload['api_workers_updated']) {
            $process = new Process([
                'supervisorctl',
                'restart',
                'frankenphp'
            ]);

            $process->run(function ($type, $buffer): void {
                if ($type === Process::OUT) {
                    echo $buffer;
                } else {
                    fwrite(STDERR, $buffer);
                }
            });
        }

        $this->goHttpApi->updateState($this->goStateFactory->create());
    }
}
