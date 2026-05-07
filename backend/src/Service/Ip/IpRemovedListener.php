<?php

namespace App\Service\Ip;

use App\Service\Ip\Event\IpRemovedEvent;
use App\Service\Send\Message\RouteNullIpsMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(IpRemovedEvent::class, 'onIpRemoved')]
class IpRemovedListener
{

    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function onIpRemoved(IpRemovedEvent $event): void
    {
        $queue = $event->getIpAddress()->getQueue();

        if ($queue === null) {
            return;
        }

        $this->bus->dispatch(new RouteNullIpsMessage($queue->getId()));
    }

}
