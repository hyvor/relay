<?php

namespace App\Service\Management\GoState;

use App\Config;
use App\Entity\Instance;
use App\Entity\Type\DnsRecordType;
use App\Service\DnsRecord\DnsRecordService;
use App\Service\Domain\Dkim;
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
        private DnsRecordService $dnsRecordService,
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

        $dnsIp = count($ips) > 0 ? $ips[0]->ip : "";

        return new GoState(
            instanceDomain: $instance->getDomain(),
            hostname: $server->getHostname(),
            ips: $ips,
            emailWorkersPerIp: $server->getEmailWorkers(),
            webhookWorkers: $server->getWebhookWorkers(),
            incomingWorkers: $server->getIncomingWorkers(),
            isLeader: $isLeader,

            // data for the DNS server
            dnsIp: $dnsIp,
            dnsRecords: $this->getDnsRecords($instance),

            serversCount: $this->serverService->getServersCount(),
            env: $this->config->getEnv(),
            version: $this->config->getAppVersion(),
        );

    }

    /**
     * @return GoStateDnsRecord[]
     */
    private function getDnsRecords(Instance $instance): array
    {

        /** @var GoStateDnsRecord[] $records */
        $records = [];
        $allIps = $this->ipAddressService->getAllIpAddresses();
        $dnsMxIps = [];
        $dnsMxIpAddedServers = [];

        // 1. Forward A records for each IP address (reverse PTR records)
        // smtp1.hyvorrelay.email -> 1.1.1.1
        foreach ($allIps as $ip) {
            if ($ip->getIsAvailable() === false || $ip->getIsEnabled() === false) {
                continue;
            }

            $records[] = new GoStateDnsRecord(
                type: DnsRecordType::A,
                host: Ptr::getPtrDomain($ip, $this->instanceService->getInstance()->getDomain()),
                content: $ip->getIpAddress(),
            );

            $serverId = $ip->getServer()->getId();

            if (!in_array($serverId, $dnsMxIpAddedServers)) {
                $dnsMxIps[] = $ip->getIpAddress();
                $dnsMxIpAddedServers[] = $serverId;
            }
        }

        // 2. MX record for the instance domain
        // hyvorrelay.email -> mx.hyvorrelay.com
        $records[] = new GoStateDnsRecord(
            type: DnsRecordType::MX,
            host: $instance->getDomain(),
            content: 'mx.' . $instance->getDomain(),
            priority: 10
        );

        // 3. A records for the MX domain
        // mx.hyvorrelay.email -> 2.2.2.2
        foreach ($dnsMxIps as $ip) {
            $records[] = new GoStateDnsRecord(
                type: DnsRecordType::A,
                host: 'mx.' . $instance->getDomain(),
                content: $ip,
            );
        }

        // 4. SPF record for the instance domain
        $records[] = new GoStateDnsRecord(
            type: DnsRecordType::TXT,
            host: $instance->getDomain(),
            content: 'v=spf1 ip4:' . implode(' ip4:', $dnsMxIps) . ' ~all',
        );

        // 5. DKIM record for the instance domain
        $records[] = new GoStateDnsRecord(
            type: DnsRecordType::TXT,
            host: 'default._domainkey.' . $instance->getDomain(),
            content: Dkim::dkimTxtValue($instance->getDkimPublicKey()),
            ttl: 3600
        );

        // 6. Custom DNS records
        $customDnsRecords = $this->dnsRecordService->getAllDnsRecords();
        foreach ($customDnsRecords as $dnsRecord) {
            $records[] = new GoStateDnsRecord(
                type: $dnsRecord->getType(),
                host: $dnsRecord->getSubdomain() ?
                    $dnsRecord->getSubdomain() . '.' . $instance->getDomain() :
                    $instance->getDomain(),
                content: $dnsRecord->getContent(),
                ttl: $dnsRecord->getTtl(),
                priority: $dnsRecord->getPriority()
            );
        }

        return $records;

    }

}