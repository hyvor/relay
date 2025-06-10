<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Entity\Server;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class IpAddressService
{

    use ClockAwareTrait;

    public function __construct(
        private ServerIp $serverIp,
        private EntityManagerInterface $em,
    )
    {
    }

    /**
     * @return IpAddress[]
     */
    public function getIpAddressesOfServer(Server $server): array
    {
        return $this->em->getRepository(IpAddress::class)->findBy(['server' => $server]);
    }

    /**
     * Creates IP address records if not already present,
     * updates existing ones to be active,
     * and deactivates those that are not present in the server's current IP addresses.
     * Does not delete any IP address records.
     */
    public function updateIpAddressesOfServer(Server $server): void
    {
        $currentIpAddressesEntitiesInDb = $this->getIpAddressesOfServer($server);
        $currentIpAddressesInDb = array_map(fn(IpAddress $ip) => $ip->getIpAddress(), $currentIpAddressesEntitiesInDb);
        $serverIpAddresses = $this->serverIp->getPublicV4IpAddresses();

        foreach ($serverIpAddresses as $serverIpAddress) {

            $inArrayKey = array_search($serverIpAddress, $currentIpAddressesInDb);

            if ($inArrayKey !== false) {
                // IP address already exists in the database, mark is as active if not active
                $ipAddressEntity = $currentIpAddressesEntitiesInDb[$inArrayKey];

                if ($ipAddressEntity->getIsActive()) {
                    continue;
                }

                $updates = new UpdateIpAddressDto();
                $updates->isActive = true;
                $this->updateIpAddress($ipAddressEntity, $updates);
            } else {
                // IP address does not exist in the database, create it
                $this->createIpAddress($server, $serverIpAddress);
            }
        }

        // Deactivate IP addresses that are in the database but not in the server's current IP addresses
        $ipAddressesToDeactivate = array_filter(
            $currentIpAddressesEntitiesInDb,
            fn(IpAddress $ip) => !in_array($ip->getIpAddress(), $serverIpAddresses)
        );
        foreach ($ipAddressesToDeactivate as $ipAddress) {
            $updates = new UpdateIpAddressDto();
            $updates->isActive = false;
            $this->updateIpAddress($ipAddress, $updates);
        }


    }

    public function createIpAddress(Server $server, string $ipAddress): IpAddress
    {
        $ipAddressEntity = new IpAddress();
        $ipAddressEntity->setServer($server);
        $ipAddressEntity->setIpAddress($ipAddress);
        $ipAddressEntity->setCreatedAt($this->now());
        $ipAddressEntity->setUpdatedAt($this->now());
        $ipAddressEntity->setIsActive(true);

        $this->em->persist($ipAddressEntity);
        $this->em->flush();

        return $ipAddressEntity;
    }

    public function updateIpAddress(
        IpAddress $ipAddress,
        UpdateIpAddressDto $updates
    ): IpAddress
    {
        if ($updates->isActiveSet) {
            $ipAddress->setIsActive($updates->isActive);
        }

        $ipAddress->setUpdatedAt($this->now());

        $this->em->persist($ipAddress);
        $this->em->flush();

        return $ipAddress;
    }

}