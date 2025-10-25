<?php

namespace App\Service\Management\MessageHandler;

use App\Service\Management\Message\PingMessage;
use App\Service\Server\Dto\UpdateServerDto;
use App\Service\Server\ServerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PingMessageHandler
{

    public function __construct(
        private ServerService $serverService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(PingMessage $message): void
    {
        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            $this->logger->warning(
                'Ping received, but no server found for the current hostname. This could indicate that the server was deleted or not initialized properly.'
            );
            return;
        }

        $updates = new UpdateServerDto();
        $updates->lastPingAt = new \DateTimeImmutable();
        $this->serverService->updateServer($server, $updates);
    }

}