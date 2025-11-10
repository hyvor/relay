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
    ) {
    }

    public function check(): bool
    {
        /** @var IpAddress[] $allIps */
        $allIps = $this->em->getRepository(IpAddress::class)
            ->createQueryBuilder('ip')
            ->where('ip.queue IS NOT NULL')
            ->getQuery()
            ->getResult();

        $invalidData = [];

        foreach ($allIps as $ip) {
            $validation = $this->ipAddressService->updateIpPtrValidity($ip);

            if (!$validation['forward']->valid || !$validation['reverse']->valid) {
                $invalidData[] = [
                    'ip' => $ip->getIpAddress(),
                    'forward_valid' => $validation['forward']->valid,
                    'forward_error' => $validation['forward']->error,
                    'reverse_valid' => $validation['reverse']->valid,
                    'reverse_error' => $validation['reverse']->error,
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
