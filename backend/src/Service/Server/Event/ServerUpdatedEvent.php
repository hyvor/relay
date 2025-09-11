<?php

namespace App\Service\Server\Event;

use App\Entity\Server;
use App\Service\Server\Dto\UpdateServerDto;

class ServerUpdatedEvent
{

    public function __construct(
        private Server $oldServer,
        private Server $server,
        private UpdateServerDto $updates,
        private bool $createUpdateStateTask
    ) {
    }

    public function getOldServer(): Server
    {
        return $this->oldServer;
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function getUpdates(): UpdateServerDto
    {
        return $this->updates;
    }

    public function shouldCreateUpdateStateTask(): bool
    {
        return $this->createUpdateStateTask;
    }

}
