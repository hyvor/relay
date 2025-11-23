<?php

namespace App\Service\Management\GoState;

use App\Entity\Instance;
use App\Entity\Type\DnsRecordType;
use App\Service\App\Config;
use App\Service\Dns\DnsRecordService;
use App\Service\Domain\Dkim;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\Ptr;
use App\Service\MxServer\MxServer;

class GoStateDnsRecordsService
{
    public function __construct(
        private IpAddressService $ipAddressService,
        private DnsRecordService $dnsRecordService,
        private Config $config,
        private MxServer $mxServer,
    ) {
    }


    /**
     * @return GoStateDnsRecord[]
     */
    public function getDnsRecords(Instance $instance, bool $custom = true): array
    {
        /** @var GoStateDnsRecord[] $records */
        $records = [];
        $allIps = $this->ipAddressService->getAllIpAddresses();
        $allIpsString = array_map(fn($ip) => $ip->getIpAddress(), $allIps);
        $dnsMxIps = [];
        $dnsMxIpAddedServers = [];

        $instanceDomain = $this->config->getInstanceDomain();

        // 1. Forward A records for each IP address (reverse PTR records)
        // smtp1.hyvorrelay.email -> 1.1.1.1
        foreach ($allIps as $ip) {
            $records[] = new GoStateDnsRecord(
                type: DnsRecordType::A,
                host: Ptr::getPtrDomain($ip, $instanceDomain),
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
            host: $instanceDomain,
            content: $this->mxServer->getMxHostname(),
            priority: 10
        );

        // 3. A records for the MX domain
        // mx.hyvorrelay.email -> 2.2.2.2
        foreach ($dnsMxIps as $ip) {
            $records[] = new GoStateDnsRecord(
                type: DnsRecordType::A,
                host: 'mx.' . $instanceDomain,
                content: $ip,
            );
        }

        // 4. SPF record for the instance domain
        $records[] = new GoStateDnsRecord(
            type: DnsRecordType::TXT,
            host: $instanceDomain,
            content: 'v=spf1 ip4:' . implode(' ip4:', $allIpsString) . ' ~all',
        );

        // 5. DKIM record for the instance domain
        $records[] = new GoStateDnsRecord(
            type: DnsRecordType::TXT,
            host: 'default._domainkey.' . $instanceDomain,
            content: Dkim::dkimTxtValue($instance->getDkimPublicKey()),
            ttl: 3600
        );

        // 6. Custom DNS records
        if ($custom) {
            $customDnsRecords = $this->dnsRecordService->getAllDnsRecords();
            foreach ($customDnsRecords as $dnsRecord) {
                $records[] = new GoStateDnsRecord(
                    type: $dnsRecord->getType(),
                    host: $dnsRecord->getSubdomain() ?
                        $dnsRecord->getSubdomain() . '.' . $instanceDomain :
                        $instanceDomain,
                    content: $dnsRecord->getContent(),
                    ttl: $dnsRecord->getTtl(),
                    priority: $dnsRecord->getPriority()
                );
            }
        }

        return $records;
    }

}
