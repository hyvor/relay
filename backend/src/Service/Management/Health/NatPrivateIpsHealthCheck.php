<?php

namespace App\Service\Management\Health;

use App\Entity\IpAddress;
use App\Entity\Server;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

/**
 * Verifies that each private IP address resolves to the expected public IP address via NAT.
 * Reads the results saved by each server's CheckNatPrivateIpsMessage.
 * This check only runs (and can fail) when IP addresses with private IPs are configured.
 */
class NatPrivateIpsHealthCheck extends HealthCheckAbstract
{

    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function check(): bool
    {
        /** @var IpAddress[] $privateIps */
        $privateIps = $this->em->createQueryBuilder()
            ->select('ip')
            ->from(IpAddress::class, 'ip')
            ->where('ip.private_ip_address IS NOT NULL')
            ->orderBy('ip.id', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var array<int, Server> $servers */
        $servers = [];
        foreach ($privateIps as $ip) {
            $servers[$ip->getServer()->getId()] = $ip->getServer();
        }

        // 2x the check interval in ServerSchedule
        $staleBefore = $this->now()->modify('-1 hour');

        $invalidNats = [];
        $staleServers = [];

        foreach ($servers as $server) {
            $natCheck = $server->getNatCheck();

            if ($natCheck === null || new \DateTimeImmutable($natCheck['checked_at']) < $staleBefore) {
                $staleServers[] = $server->getHostname();
                continue;
            }

            foreach ($natCheck['invalid_nats'] as $invalidNat) {
                $invalidNats[] = [
                    'server_hostname' => $server->getHostname(),
                    'private_ip' => $invalidNat['private_ip'],
                    'expected_public_ip' => $invalidNat['expected_public_ip'],
                    'resolved_public_ip' => $invalidNat['resolved_public_ip'],
                    'error' => $invalidNat['error'],
                ];
            }
        }

        if (count($invalidNats) > 0 || count($staleServers) > 0) {
            $this->setData([
                'invalid_nats' => $invalidNats,
                'stale_servers' => $staleServers,
            ]);
            return false;
        }

        return true;
    }
}
