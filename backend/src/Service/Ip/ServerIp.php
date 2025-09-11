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
    ) {
    }

    /**
     * Gets all IP addresses of the server.
     * @return string[]
     */
    public function getPublicV4IpAddresses(): array
    {
        $allIps = $this->getAllIpAddresses();

        $publicIps = [];

        foreach ($allIps as $ip) {
            if ($this->isPublicIpv4($ip)) {
                $publicIps[] = $ip;
            }
        }

        return $publicIps;
    }

    private function isPublicIpv4(string $ip): bool
    {
        // must be IPV4
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $privateRanges = IpUtils::PRIVATE_SUBNETS;
        $privateRanges[] = '100.64.0.0/10'; // CGNAT

        if (IpUtils::checkIp($ip, $privateRanges)) {
            return false;
        }

        return true;
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
