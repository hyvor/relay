<?php

namespace App\Service\Instance\Event;

use App\Entity\Instance;
use App\Service\Instance\Dto\UpdateInstanceDto;

readonly class InstanceUpdatedEvent
{

    public function __construct(
        private Instance $oldInstance,
        private Instance $newInstance,
        private UpdateInstanceDto $updates,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getOldInstance(): Instance
    {
        return $this->oldInstance;
    }

    public function getNewInstance(): Instance
    {
        return $this->newInstance;
    }

    public function getUpdates(): UpdateInstanceDto
    {
        return $this->updates;
    }

}