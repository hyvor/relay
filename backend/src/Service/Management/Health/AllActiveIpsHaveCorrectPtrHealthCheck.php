<?php

namespace App\Service\Management\Health;

use App\Entity\IpAddress;
use App\Service\Ip\Ptr;
use Doctrine\ORM\EntityManagerInterface;

class AllActiveIpsHaveCorrectPtrHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private EntityManagerInterface $em,
        private Ptr $ptr,
        /**
         * @var callable
         */
        private $gethostbyaddr = 'gethostbyaddr'
    )
    {
    }

    public function check(): bool
    {

        $allIps = $this->em->getRepository(IpAddress::class)
            ->findBy(['isActive' => true, 'isEnabled' => true]);

        $ipsWithIncorrectPtr = [];

        foreach ($allIps as $ip) {
            $ptr = call_user_func($this->gethostbyaddr, $ip->getIpAddress());



            if ($ptr === $ip->getIpAddress() || empty($ptr)) {
                $ipsWithIncorrectPtr[] = $ip->getIpAddress();
            }
        }



    }
}