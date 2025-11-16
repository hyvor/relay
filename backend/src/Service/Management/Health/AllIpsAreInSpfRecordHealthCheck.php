<?php

namespace App\Service\Management\Health;

use App\Service\App\Config;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use SPFLib\Check\Result;
use SPFLib\Checker;
use SPFLib\Check\Environment;
use SPFLib\DNS\Resolver;
use SPFLib\Exception\InvalidIPAddressException;


class AllIpsAreInSpfRecordHealthCheck extends HealthCheckAbstract
{
    public function __construct(
        private InstanceService $instanceService,
        private IpAddressService $ipAddressService,
        private Config $config,
        private ?Resolver $dnsResolver = null,
    ) {
    }

    public function check(): bool
    {
        $instance = $this->instanceService->getInstance();
        $ip_addresses = $this->ipAddressService->getAllIpAddresses();
        $invalid_ips = [];
        foreach ($ip_addresses as $ipAddress) {
            $ip = $ipAddress->getIpAddress();
            $checker = new Checker(dnsResolver: $this->dnsResolver);
            try {
                $checkResult = $checker->check(new Environment($ip, $this->config->getInstanceDomain()));
                if ($checkResult->getCode() !== Result::CODE_PASS) {
                    $invalid_ips[] = $ip;
                }
            } catch (InvalidIPAddressException $e) {
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
