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

    public function updateIpAddressesOfServer(Server $server): void
    {
        $currentIpAddressesEntitiesInDb = $this->getIpAddressesOfServer($server);
        $currentIpAddressesInDb = array_map(fn(IpAddress $ip) => $ip->getIpAddress(), $currentIpAddressesEntitiesInDb);
        $serverIpAddresses = $this->serverIp->getPublicV4IpAddresses();
        $ipAddressesToAdd = array_diff($serverIpAddresses, $currentIpAddressesInDb);

        //
    }

    public function createIpAddress(Server $server, string $ipAddress): IpAddress
    {
        $ipAddressEntity = new IpAddress();
        $ipAddressEntity->setServer($server);
        $ipAddressEntity->setIpAddress($ipAddress);
        $ipAddressEntity->setCreatedAt($this->now());
        $ipAddressEntity->setUpdatedAt($this->now());

        $this->em->persist($ipAddressEntity);
        $this->em->flush();

        return $ipAddressEntity;
    }

}