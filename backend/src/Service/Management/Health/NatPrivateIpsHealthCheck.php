<?php

namespace App\Service\Management\Health;

use App\Entity\IpAddress;
use App\Service\Ip\PublicIpResolver;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verifies that each private IP address resolves to the expected public IP address via NAT.
 * This check only runs (and can fail) when IP addresses with private IPs are configured.
 */
class NatPrivateIpsHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private EntityManagerInterface $em,
        private PublicIpResolver $publicIpResolver,
    ) {
    }

    public function check(): bool
    {
        /** @var IpAddress[] $allIps */
        $allIps = $this->em->getRepository(IpAddress::class)->findAll();

        $invalidNats = [];
        $privateIpsExist = false;

        foreach ($allIps as $ip) {
            if ($ip->getPrivateIpAddress() === null) {
                continue;
            }

            $privateIpsExist = true;
            $privateIp = $ip->getPrivateIpAddress();

            try {
                $resolvedPublicIp = $this->publicIpResolver->resolve($privateIp);

                if ($resolvedPublicIp !== $ip->getIpAddress()) {
                    $invalidNats[] = [
                        'private_ip' => $privateIp,
                        'expected_public_ip' => $ip->getIpAddress(),
                        'resolved_public_ip' => $resolvedPublicIp,
                        'error' => null,
                    ];
                }
            } catch (\Throwable $e) {
                $invalidNats[] = [
                    'private_ip' => $privateIp,
                    'expected_public_ip' => $ip->getIpAddress(),
                    'resolved_public_ip' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (!$privateIpsExist) {
            return true;
        }

        if (count($invalidNats) > 0) {
            $this->setData(['invalid_nats' => $invalidNats]);
            return false;
        }

        return true;
    }
}
