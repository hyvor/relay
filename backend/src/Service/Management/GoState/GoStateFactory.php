<?php

namespace App\Service\Management\GoState;

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
                incoming: false,
            );
        }

        return new GoState(
            instanceDomain: $instance->getDomain(),
            hostname: $server->getHostname(),
            ips: $ips,
            emailWorkersPerIp: $server->getEmailWorkers(),
            webhookWorkers: $server->getWebhookWorkers() + 1
        );

    }

}