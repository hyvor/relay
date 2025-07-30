<?php

namespace App\Service\Management\Health;

use App\Entity\Server;
use App\Service\PrivateNetwork\Exception\PrivateNetworkCallException;
use App\Service\PrivateNetwork\PrivateNetworkApi;
use App\Service\Server\ServerService;
use Doctrine\ORM\EntityManagerInterface;

class AllServersCanBeReachedViaPrivateNetwork extends HealthCheckAbstract
{
    public function __construct(
        private PrivateNetworkApi $privateNetworkApi,
        private ServerService $serverService
    )
    {
    }

    public function check(): bool
    {
        $servers = $this->serverService->getServers();
        $currentServer = $this->serverService->getServerByCurrentHostname();
        $unreachableServers = [];

        foreach ($servers as $server) {
            if ($server->getPrivateIp() === null) {
                $unreachableServers[] = $server->getHostname();
                continue;
            }
            try {
                $this->privateNetworkApi->pingServer($server);
            } catch (PrivateNetworkCallException $e) {
                $unreachableServers[] = $server->getHostname();
            }
        }
        if (count($unreachableServers) > 0) {
            $this->setData([
                'unreachable_servers' => $unreachableServers,
                'checking_server' => $currentServer?->getHostname() ?? 'unknown'
            ]);
            return false;
        }

        return true;
    }
}
