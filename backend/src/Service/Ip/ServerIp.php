<?php

namespace App\Service\Ip;

class ServerIp
{

    public function __construct(
        private mixed $netGetInterfacesFunction = '\net_get_interfaces',
    )
    {}

    /**
     * Gets all IP addresses of the server.
     * This method cannot be tested reliably in a test environment
     * @return string[]
     */
    public function getPublicV4IpAddresses(): array
    {
        $ips = [];
        $interfaces = call_user_func($this->netGetInterfacesFunction);

        foreach ($interfaces as $interface) {
            if ($interface['up'] === false) {
                continue;
            }

            $unicast = $interface['unicast'];

            foreach ($unicast as $address) {
                if (empty($address['address'])) {
                    continue;
                }

                $ips[] = $address['address'];
            }
        }

        // Remove duplicates
        $ips = array_unique($ips);

        // Sort the IPs
        sort($ips);

        return $this->filterPublicV4Ips($ips);
    }

    /**
     * Filters the public IP addresses from the given list of all IPs.
     * @param string[] $allIps
     * @return string[]
     */
    private function filterPublicV4Ips(array $allIps): array
    {
        $publicIps = [];

        foreach ($allIps as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $publicIps[] = $ip;
            }
        }

        return $publicIps;
    }

}