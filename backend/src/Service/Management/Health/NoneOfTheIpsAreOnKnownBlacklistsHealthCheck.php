<?php

namespace App\Service\Management\Health;

use App\Service\Blacklist\DnsblQuery;
use App\Service\Blacklist\Exception\DnsblLookupException;
use App\Service\Blacklist\IpBlacklists;
use App\Service\Ip\IpAddressService;

class NoneOfTheIpsAreOnKnownBlacklistsHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private IpAddressService $ipAddressService,
        private DnsblQuery $dnsblQuery
    ) {
    }

    // Note: implement concurrency in the future
    public function check(): bool
    {
        $ips = $this->getAllSendingIps();
        $blacklists = IpBlacklists::getBlacklists();

        /** @var array{duration_ms: float, lists: array<string, array<string, array{duration_ms: int, status: 'ok'|'blocked'|'error', resolved_ip?: string, error?: string}>>} $data */
        $data = [
            'lists' => [],
        ];
        $passed = true;

        $allStartTime = microtime(true);
        foreach ($blacklists as $blacklist) {
            foreach ($ips as $ip) {
                $ipData = [];

                try {
                    $startTime = microtime(true);
                    $result = $this->dnsblQuery->query($blacklist, $ip);
                    $ipData['duration_ms'] = (int)round((microtime(true) - $startTime) * 1000);

                    if ($result->isBlocked()) {
                        $ipData['status'] = "blocked";
                        $ipData['resolved_ip'] = $result->getResolvedIp();
                        $passed = false;
                    } else {
                        $ipData['status'] = "ok";
                    }
                } catch (DnsblLookupException $e) {
                    $ipData['status'] = "error";
                    $ipData['error'] = $e->getMessage();
                }

                $data['lists'][$blacklist->getId()][$ip] = $ipData;
            }
        }
        $data['duration_ms'] = (int)round((microtime(true) - $allStartTime) * 1000);

        $this->setData($data);

        return $passed;
    }

    /**
     * @return string[]
     */
    private function getAllSendingIps(): array
    {
        $ips = [];
        foreach ($this->ipAddressService->getAllIpAddresses() as $ipAddress) {
            if ($ipAddress->getQueue() === null) {
                continue;
            }
            $ips[] = $ipAddress->getIpAddress();
        }
        return $ips;
    }

}