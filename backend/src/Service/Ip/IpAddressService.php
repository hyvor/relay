<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Entity\Queue;
use App\Entity\Server;
use App\Service\Ip\Dto\PtrValidationDto;
use App\Service\Ip\Dto\UpdateIpAddressDto;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Ip\Event\IpAddressCreatedEvent;
use App\Service\Ip\Event\IpAddressRemovedEvent;
use App\Service\Ip\ServerIpResolver\ServerIpResolver;
use App\Service\Ip\ServerIpResolver\ResolvedIp;
use App\Service\Queue\QueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class IpAddressService
{

    use ClockAwareTrait;

    public function __construct(
        private ServerIpResolver $serverIpResolver,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
        private Ptr $ptr,
        private QueueService $queueService,
    ) {}

    /**
     * @return IpAddress[]
     */
    public function getAllIpAddresses(): array
    {
        return $this->em->getRepository(IpAddress::class)->findBy(
            [],
            ['id' => 'ASC'],
        );
    }

    public function getIpAddressesCount(): int
    {
        return $this->em->getRepository(IpAddress::class)->count([]);
    }

    public function getIpAddressById(int $id): ?IpAddress
    {
        return $this->em->getRepository(IpAddress::class)->find($id);
    }

    /**
     * @return IpAddress[]
     */
    public function getIpAddressesOfServer(Server $server): array
    {
        return $this->em->getRepository(IpAddress::class)->findBy(
            ['server' => $server],
            ['id' => 'ASC'],
        );
    }

    /**
     * Creates IP address records if not already present.
     * Updates private IP address if it has changed.
     * Deletes IP address records that are no longer present on the server.
     */
    public function updateIpAddressesOfServer(Server $server): void
    {
        $resolvedIpsToCreate = [];
        foreach ($this->serverIpResolver->resolveIps() as $resolvedIp) {
            $resolvedIpsToCreate[$resolvedIp->publicIp] = $resolvedIp;
        }

        $events = [];
        $deletedIpAddressIds = [];

        foreach ($this->getIpAddressesOfServer($server) as $entity) {
            $resolvedIp = $resolvedIpsToCreate[$entity->getIpAddress()] ?? null;

            if ($resolvedIp === null) {
                $deletedIpAddressIds[] = $entity->getId();
                $this->deleteIpAddress($entity, flush: false, events: $events);
                continue;
            }

            unset($resolvedIpsToCreate[$entity->getIpAddress()]);

            if ($entity->getPrivateIpAddress() !== $resolvedIp->privateIp) {
                $entity->setPrivateIpAddress($resolvedIp->privateIp);
                $entity->setUpdatedAt($this->now());
            }
        }

        $freeQueues = $this->queueService->getQueuesThatHaveNoIpAddresses($deletedIpAddressIds);

        foreach ($resolvedIpsToCreate as $resolvedIp) {
            $this->createIpAddress(
                $server,
                $resolvedIp->publicIp,
                $resolvedIp->privateIp,
                array_shift($freeQueues),
                flush: false,
                events: $events,
            );
        }

        $this->em->flush();

        foreach ($events as $event) {
            $this->ed->dispatch($event);
        }
    }

    /**
     * @param object[] $events
     */
    public function createIpAddress(
        Server $server,
        string $ipAddress,
        ?string $privateIpAddress = null,
        ?Queue $queue = null,
        bool $flush = true,
        array &$events = [],
    ): IpAddress {
        $ipAddressEntity = new IpAddress();
        $ipAddressEntity->setServer($server);
        $ipAddressEntity->setIpAddress($ipAddress);
        $ipAddressEntity->setPrivateIpAddress($privateIpAddress);
        $ipAddressEntity->setCreatedAt($this->now());
        $ipAddressEntity->setUpdatedAt($this->now());
        $ipAddressEntity->setQueue($queue);

        $this->em->persist($ipAddressEntity);
        $event = new IpAddressCreatedEvent($ipAddressEntity);

        if ($flush) {
            $this->em->flush();
            $this->ed->dispatch($event);
        } else {
            $events[] = $event;
        }

        return $ipAddressEntity;
    }

    /**
     * @param object[] $events
     */
    public function deleteIpAddress(IpAddress $ipAddress, bool $flush = true, array &$events = []): void
    {
        $this->em->remove($ipAddress);
        $event = new IpAddressRemovedEvent($ipAddress);

        if ($flush) {
            $this->em->flush();
            $this->ed->dispatch($event);
        } else {
            $events[] = $event;
        }
    }

    public function updateIpAddress(
        IpAddress $ipAddress,
        UpdateIpAddressDto $updates,
    ): IpAddress {
        $ipAddressOld = clone $ipAddress;

        if ($updates->queueSet) {
            $ipAddress->setQueue($updates->queue);
        }

        $ipAddress->setUpdatedAt($this->now());
        $this->em->persist($ipAddress);
        $this->em->flush();

        $this->ed->dispatch(new IpAddressUpdatedEvent($ipAddressOld, $ipAddress, $updates));

        return $ipAddress;
    }

    /**
     * @return array{forward: PtrValidationDto, reverse: PtrValidationDto}
     */
    public function updateIpPtrValidity(IpAddress $ip): array
    {
        $validity = $this->ptr->validate($ip);

        $ip->setIsPtrForwardValid($validity['forward']->valid);
        $ip->setIsPtrReverseValid($validity['reverse']->valid);

        $this->em->persist($ip);
        $this->em->flush();

        return $validity;
    }
}
