<?php

namespace App\Service\ServerTask;

use App\Service\Dns\Event\CustomDnsRecordsChangedEvent;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Server\Event\ServerUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(ServerUpdatedEvent::class, 'onServerUpdated')]
#[AsEventListener(IpAddressUpdatedEvent::class, 'onIpAddressUpdated')]
#[AsEventListener(CustomDnsRecordsChangedEvent::class, 'onCustomDnsRecordsChanged')]
class UpdateStateTaskListener
{

    public function __construct(
        private ServerTaskService $serverTaskService,
    ) {
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

    public function onCustomDnsRecordsChanged(CustomDnsRecordsChangedEvent $event): void
    {
        $this->serverTaskService->createUpdateStateTask(null);
    }

}
