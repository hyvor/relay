<?php

namespace App\Service\ServerTask;

use App\Service\Instance\Event\InstanceUpdatedEvent;
use App\Service\Server\Event\ServerUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(InstanceUpdatedEvent::class, 'onInstanceUpdated')]
#[AsEventListener(ServerUpdatedEvent::class, 'onServerUpdated')]
class UpdateStateTaskListener
{

    public function __construct(
        private ServerTaskService $serverTaskService,
    ) {
    }


    public function onInstanceUpdated(InstanceUpdatedEvent $event): void
    {
        if ($event->getUpdates()->domainSet) {
            $this->serverTaskService->createUpdateStateTask(null);
        }
    }

    public function onServerUpdated(ServerUpdatedEvent $event): void
    {
        if (!$event->shouldCreateUpdateStateTask()) {
            return;
        }

        $this->serverTaskService->createUpdateStateTask(
            $event->getServer(),
            apiWorkersUpdated: $event->getUpdates()->apiWorkersSet,
        );
    }

}
