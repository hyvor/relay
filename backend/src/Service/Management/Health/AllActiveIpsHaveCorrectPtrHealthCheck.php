<?php

namespace App\Service\Management\Health;

use App\Entity\IpAddress;
use App\Service\App\Config;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\Ptr;
use Doctrine\ORM\EntityManagerInterface;

class AllActiveIpsHaveCorrectPtrHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private EntityManagerInterface $em,
        private IpAddressService $ipAddressService,
        private Config $config,
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
                    // the hostname both lookups are checked against. Without
                    // it the console can only report that the PTR is wrong,
                    // not what it should have been.
                    'expected_ptr' => Ptr::getPtrDomain($ip, $this->config->getInstanceDomain()),
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
