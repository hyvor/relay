<?php

namespace App\Service\Management\Health;

use App\Entity\IpAddress;
use App\Service\Ip\IpAddressService;
use Doctrine\ORM\EntityManagerInterface;

class AllActiveIpsHaveCorrectPtrHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private EntityManagerInterface $em,
        private IpAddressService $ipAddressService,
    )
    {
    }

    public function check(): bool
    {

        $allIps = $this->em->getRepository(IpAddress::class)
            ->findBy(['isActive' => true, 'isEnabled' => true]);

        $invalidData = [];

        foreach ($allIps as $ip) {
            $this->ipAddressService->updateIpPtrValidity($ip);

            if ($ip->getIsPtrForwardValid() || $ip->getIsPtrReverseValid()) {
                $invalidData[] = [
                    'ip' => $ip->getIpAddress(),
                    'forward_valid' => $ip->getIsPtrForwardValid(),
                    'reverse_valid' => $ip->getIsPtrReverseValid(),
                ];
            }
        }

        if (count($invalidData) > 0) {
            $this->setData([
                'invalid_ptrs' => $invalidData,
            ]);
            return false;
        }

        return true;

    }
}