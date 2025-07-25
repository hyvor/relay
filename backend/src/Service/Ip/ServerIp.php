<?php

namespace App\Service\Ip;

use Symfony\Component\HttpFoundation\IpUtils;

class ServerIp
{

    /**
     * @param callable $netGetInterfacesFunction
     */
    public function __construct(
        private $netGetInterfacesFunction = 'net_get_interfaces',
    )
    {}

    /**
     * Gets all IP addresses of the server.
     * @return string[]
     */
    public function getPublicV4IpAddresses(): array
    {
        $allIps = $this->getAllIpAddresses();

        $publicIps = [];

        foreach ($allIps as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $publicIps[] = $ip;
            }
        }

        return $publicIps;
    }

    /**
     * Gets a private IP address in the 10.0.0.0/8 range
     */
    public function getPrivateIp(): ?string
    {
        $allIps = $this->getAllIpAddresses();

        foreach ($allIps as $ip) {
            if (IpUtils::checkIp4($ip, "10.0.0.0/8")) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Gets all available IP addresses of the server.
     * @return string[]
     */
    private function getAllIpAddresses(): array
    {
        /** @var string[] $ips */
        $ips = [];

        $interfaces = call_user_func($this->netGetInterfacesFunction);

        if (!is_array($interfaces)) {
            return [];
        }

        foreach ($interfaces as $interface) {
            if (!is_array($interface) || !isset($interface['up']) || $interface['up'] === false) {
                continue;
            }

            if (!isset($interface['unicast']) || !is_array($interface['unicast'])) {
                continue;
            }

            $unicast = $interface['unicast'];

            foreach ($unicast as $address) {
                if (!is_array($address) || empty($address['address']) || !is_string($address['address'])) {
                    continue;
                }

                $ips[] = $address['address'];
            }
        }

        // Remove duplicates
        $ips = array_unique($ips);

        // Sort the IPs
        sort($ips);

        return $ips;
    }

}