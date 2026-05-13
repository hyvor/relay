<?php

namespace App\Service\Ip;

use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Send\Message\RouteQueueNullIpsToIpMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(IpAddressUpdatedEvent::class, 'onIpAddressUpdated')]
class IpAddressQueueAssignedListener
{

    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function onIpAddressUpdated(IpAddressUpdatedEvent $event): void
    {
        $updates = $event->getUpdates();

        if (!$updates->queueSet) {
            return;
        }

        $ipAddress = $event->getIpAddress();
        $queue = $ipAddress->getQueue();

        if ($queue === null) {
            return;
        }

        $this->bus->dispatch(new RouteQueueNullIpsToIpMessage(
            $queue->getId(),
            $ipAddress->getId()
        ));
    }

}
