<?php

namespace App\Service\ServerTask;

use App\Service\Instance\Event\InstanceUpdatedEvent;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Server\Event\ServerUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(InstanceUpdatedEvent::class, 'onInstanceUpdated')]
#[AsEventListener(ServerUpdatedEvent::class, 'onServerUpdated')]
#[AsEventListener(IpAddressUpdatedEvent::class, 'onIpAddressUpdated')]
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

    public function onIpAddressUpdated(IpAddressUpdatedEvent $event): void
    {
        if ($event->getUpdates()->queueSet) {
            $server = $event->getIpAddress()->getServer();
            $this->serverTaskService->createUpdateStateTask($server);
        }
    }

}
