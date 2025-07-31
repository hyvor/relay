<?php

namespace App\Service\Management\Health;

use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use SPFLib\Check\Result;
use SPFLib\Checker;
use SPFLib\Check\Environment;


class AllIpsAreInSpfRecordHealthCheck extends HealthCheckAbstract
{
    public function __construct(
        private InstanceService $instanceService,
        private IpAddressService $ipAddressService,
    )
    {}

    public function check(): bool
    {
        $instance = $this->instanceService->getInstance();
        $domain = $instance->getDomain();
        $ip_addresses = $this->ipAddressService->getAllIpAddresses();
        $invalid_ips = [];
        foreach ($ip_addresses as $ipAddress) {
            $ip = $ipAddress->getIpAddress();
            $checker = new Checker();
            $checkResult = $checker->check(new Environment($ip, $domain));
            if ($checkResult->getCode() !== Result::CODE_PASS) {
                $invalid_ips[] = $ip;
            }
        }
        if (count($invalid_ips) > 0) {
            $this->setData([
                'invalid_ips' => $invalid_ips,
                'domain' => $domain,
            ]);
            return false;
        }
        return true;
    }
}
