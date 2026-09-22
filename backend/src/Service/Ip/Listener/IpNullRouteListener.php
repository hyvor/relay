<?php

namespace App\Service\Ip\Listener;

use App\Entity\Queue;
use App\Entity\Send;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Ip\Event\IpAddressCreatedEvent;
use App\Service\Ip\Event\IpAddressRemovedEvent;
use App\Service\Send\Message\RouteNullIpsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(IpAddressCreatedEvent::class, 'onIpCreated')]
#[AsEventListener(IpAddressUpdatedEvent::class, 'onIpUpdated')]
#[AsEventListener(IpAddressRemovedEvent::class, 'onIpRemoved')]
class IpNullRouteListener
{

    public function __construct(
        private MessageBusInterface $bus,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function onIpCreated(IpAddressCreatedEvent $event): void
    {
        $ipAddress = $event->getIpAddress();
        $queue = $ipAddress->getQueue();

        if ($queue === null) {
            return;
        }

        $this->dispatchIfNeeded($queue);
    }

    public function onIpUpdated(IpAddressUpdatedEvent $event): void
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

        $this->dispatchIfNeeded($queue);
    }

    public function onIpRemoved(IpAddressRemovedEvent $event): void
    {
        $queue = $event->getIpAddress()->getQueue();

        if ($queue === null) {
            return;
        }

        $this->dispatchIfNeeded($queue);
    }

    private function dispatchIfNeeded(Queue $queue): void
    {
        $has = $this->em
            ->getRepository(Send::class)->createQueryBuilder('s')
            ->select('1')
            ->where('s.queue = :queue')
            ->andWhere('s.ip_address IS NULL')
            ->andWhere('s.queued = true')
            ->setParameter('queue', $queue)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($has === null) {
            return;
        }

        try {
            $this->bus->dispatch(new RouteNullIpsMessage($queue->getId()));
        } catch (\Throwable $e) { // @codeCoverageIgnoreStart
            $this->logger->error('Failed to dispatch RouteNullIpsMessage', [
                'queueId' => $queue->getId(),
                'exception' => $e,
            ]);
        } // @codeCoverageIgnoreEnd
    }

}
