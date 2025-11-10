<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Entity\Server;
use App\Service\Ip\Dto\PtrValidationDto;
use App\Service\Ip\Dto\UpdateIpAddressDto;
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
     * Deletes IP address records that are not present in the server's current IP addresses.
     */
    public function updateIpAddressesOfServer(Server $server): void
    {
        $currentIpAddressesEntitiesInDb = $this->getIpAddressesOfServer($server);
        $currentIpAddressesInDb = array_map(fn(IpAddress $ip) => $ip->getIpAddress(), $currentIpAddressesEntitiesInDb);
        $serverIpAddresses = $this->serverIp->getPublicV4IpAddresses();

        // Create IP addresses that are in the server's current IP addresses but not in the database
        foreach ($serverIpAddresses as $serverIpAddress) {
            $inArrayKey = in_array($serverIpAddress, $currentIpAddressesInDb);
            if ($inArrayKey === false) {
                $this->createIpAddress($server, $serverIpAddress);
            }
        }

        // Delete IP addresses that are in the database but not in the server's current IP addresses
        $ipAddressesToDelete = array_filter(
            $currentIpAddressesEntitiesInDb,
            fn(IpAddress $ip) => !in_array($ip->getIpAddress(), $serverIpAddresses)
        );
        foreach ($ipAddressesToDelete as $ipAddress) {
            $this->deleteIpAddress($ipAddress);
        }
    }

    public function createIpAddress(Server $server, string $ipAddress): IpAddress
    {
        $ipAddressEntity = new IpAddress();
        $ipAddressEntity->setServer($server);
        $ipAddressEntity->setIpAddress($ipAddress);
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
