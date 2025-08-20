<?php

namespace App\Service\Management\MessageHandler;

use App\Entity\Type\ServerTaskType;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\Message\PingMessage;
use App\Service\Management\Message\ServerTaskMessage;
use App\Service\PrivateNetwork\GoHttpApi;
use App\Service\Server\Dto\UpdateServerDto;
use App\Service\Server\ServerService;
use App\Service\Server\ServerTaskService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
                $this->goHttpApi->updateState($this->goStateFactory->create());
            $this->serverTaskService->deleteTask($task);
        }
    }

}
