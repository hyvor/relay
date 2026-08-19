<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Entity\Server;
use App\Service\Ip\Dto\PtrValidationDto;
use App\Service\Ip\Dto\UpdateIpAddressDto;
use App\Service\Ip\ServerIpResult;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Queue\QueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class IpAddressService
{

    use ClockAwareTrait;

    public function __construct(
        private ServerIp $serverIp,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
        private Ptr $ptr,
        private QueueService $queueService,
    ) {
    }

    /**
     * @return IpAddress[]
     */
    public function getAllIpAddresses(): array
    {
        return $this->em->getRepository(IpAddress::class)->findBy(
            [],
            ['id' => 'ASC']
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
            ['id' => 'ASC']
        );
    }

    /**
     * Creates IP address records if not already present.
     * Updates private IP address if it has changed.
     * Deletes IP address records that are no longer present on the server.
     */
    public function updateIpAddressesOfServer(Server $server): void
    {
        $currentEntitiesInDb = $this->getIpAddressesOfServer($server);

        /** @var array<string, IpAddress> $entitiesByPublicIp */
        $entitiesByPublicIp = [];
        foreach ($currentEntitiesInDb as $entity) {
            $entitiesByPublicIp[$entity->getIpAddress()] = $entity;
        }

        $serverIpData = $this->serverIp->getServerIpData();

        $serverPublicIps = array_map(fn(ServerIpResult $r) => $r->publicIp, $serverIpData);

        foreach ($serverIpData as $result) {
            if (!isset($entitiesByPublicIp[$result->publicIp])) {
                $this->createIpAddress($server, $result->publicIp, $result->privateIp);
            } elseif ($entitiesByPublicIp[$result->publicIp]->getPrivateIpAddress() !== $result->privateIp) {
                $entity = $entitiesByPublicIp[$result->publicIp];
                $entity->setPrivateIpAddress($result->privateIp);
                $entity->setUpdatedAt($this->now());
                $this->em->persist($entity);
                $this->em->flush();
            }
        }

        // Delete IP addresses no longer on the server
        foreach ($currentEntitiesInDb as $entity) {
            if (!in_array($entity->getIpAddress(), $serverPublicIps)) {
                $this->deleteIpAddress($entity);
            }
        }
    }

    public function createIpAddress(Server $server, string $ipAddress, ?string $privateIpAddress = null): IpAddress
    {
        $ipAddressEntity = new IpAddress();
        $ipAddressEntity->setServer($server);
        $ipAddressEntity->setIpAddress($ipAddress);
        $ipAddressEntity->setPrivateIpAddress($privateIpAddress);
        $ipAddressEntity->setCreatedAt($this->now());
        $ipAddressEntity->setUpdatedAt($this->now());
        $ipAddressEntity->setQueue($this->queueService->getAQueueThatHasNoIpAddresses());

        $this->em->persist($ipAddressEntity);
        $this->em->flush();

        return $ipAddressEntity;
    }

    public function deleteIpAddress(IpAddress $ipAddress): void
    {
        $this->em->remove($ipAddress);
        $this->em->flush();
    }

    public function updateIpAddress(
        IpAddress $ipAddress,
        UpdateIpAddressDto $updates
    ): IpAddress {
        $ipAddressOld = clone $ipAddress;

        if ($updates->queueSet) {
            $ipAddress->setQueue($updates->queue);
        }

        $ipAddress->setUpdatedAt($this->now());

        $this->em->persist($ipAddress);
        $this->em->flush();

        $event = new IpAddressUpdatedEvent($ipAddressOld, $ipAddress, $updates);
        $this->ed->dispatch($event);

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
