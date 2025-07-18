<?php

namespace App\Service\Management\GoState;

use App\Config;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\Ptr;
use App\Service\Server\ServerService;

class GoStateFactory
{

    public function __construct(
        private ServerService $serverService,
        private IpAddressService $ipAddressService,
        private InstanceService $instanceService,
        private Config $config,
    )
    {
    }

    public function create(): GoState
    {
        $instance = $this->instanceService->getInstance();

        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            throw new ServerNotFoundException();
        }

        $isLeader = $this->serverService->isServerLeader($server);
        $ips = [];

        $ipsFromServer = $this->ipAddressService->getIpAddressesOfServer($server);

        foreach ($ipsFromServer as $ip) {
            if ($ip->getIsAvailable() === false || $ip->getIsEnabled() === false) {
                continue;
            }

            $queue = $ip->getQueue();

            if ($queue === null) {
                continue;
            }

            $ips[] = new GoStateIp(
                id: $ip->getId(),
                ip: $ip->getIpAddress(),
                ptr: Ptr::getPtrDomain($ip, $instance->getDomain()),
                queueId: $queue->getId(),
                queueName: $queue->getName(),
            );
        }

        $allIps = $this->ipAddressService->getAllIpAddresses();
        $dnsPtrForwardRecords = [];
        $dnsMxIps = [];
        $dnsMxIpAddedServers = [];

        foreach ($allIps as $ip) {
            if ($ip->getIsAvailable() === false || $ip->getIsEnabled() === false) {
                continue;
            }
            $dnsPtrForwardRecords[Ptr::getPtrDomain($ip, $instance->getDomain())] = $ip->getIpAddress();

            $serverId = $ip->getServer()->getId();

            if (!in_array($serverId, $dnsMxIpAddedServers)) {
                $dnsMxIps[] = $ip->getIpAddress();
                $dnsMxIpAddedServers[] = $serverId;
            }
        }

        return new GoState(
            instanceDomain: $instance->getDomain(),
            hostname: $server->getHostname(),
            ips: $ips,
            emailWorkersPerIp: $server->getEmailWorkers() + 4,
            webhookWorkers: $server->getWebhookWorkers() + 1, // TODO:
            isLeader: $isLeader,

            // data for the DNS server
            dnsServer: true, // TODO: add this as a server config
            dnsPtrForwardRecords: $dnsPtrForwardRecords,
            dnsMxIps: $dnsMxIps,

            serversCount: $this->serverService->getServersCount(),
            env: $this->config->getEnv(),
            version: $this->config->getAppVersion(),
        );

    }

}