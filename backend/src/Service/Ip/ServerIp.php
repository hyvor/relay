<?php

namespace App\Service\Ip;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

class ServerIp
{
    /**
     * @param callable $netGetInterfacesFunction
     */
    public function __construct(
        #[Autowire('%env(string:PRIVATE_NETWORK)%')]
        private string $privateNetwork = '',
        #[Autowire('%env(string:NAT_MAP)%')]
        private string $natMap = '',
        private ?PublicIpResolver $publicIpResolver = null,
        private LoggerInterface $logger = new NullLogger(),
        private $netGetInterfacesFunction = 'net_get_interfaces',
    ) {
    }

    /**
     * Returns IP address data for the server.
     * When PRIVATE_NETWORK is configured, detects private IPs in those ranges and maps
     * each to its public IP via NAT_MAP or external resolution (ifconfig.me / icanhazip.com).
     * Without PRIVATE_NETWORK, falls back to detecting public IPv4 addresses directly.
     *
     * @return ServerIpResult[]
     * @throws \RuntimeException if PRIVATE_NETWORK is set but no matching private IPs are found,
     *                           or if public IP resolution fails
     */
    public function getServerIpData(): array
    {
        if ($this->privateNetwork !== '') {
            return $this->getNatIpData();
        }

        $publicIps = $this->getPublicV4IpAddresses();
        $this->logger->info('Detected public IP addresses', ['ips' => $publicIps]);

        return array_map(fn(string $ip) => new ServerIpResult($ip), $publicIps);
    }

    /**
     * Gets all public IPv4 addresses of the server (without NAT).
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

    /**
     * @return ServerIpResult[]
     */
    private function getNatIpData(): array
    {
        $privateRanges = $this->parsePrivateNetwork();
        $this->logger->info('NAT mode: scanning for private IPs in configured ranges', [
            'private_network' => $this->privateNetwork,
        ]);

        $allIps = $this->getAllIpAddresses();
        $privateIps = [];

        foreach ($allIps as $ip) {
            if (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
                IpUtils::checkIp($ip, $privateRanges)
            ) {
                $privateIps[] = $ip;
                $this->logger->debug('Found private IP matching PRIVATE_NETWORK', ['ip' => $ip]);
            }
        }

        if (empty($privateIps)) {
            throw new \RuntimeException(
                "NAT mode is enabled (PRIVATE_NETWORK=$this->privateNetwork) but no matching " .
                "private IPv4 addresses were found on this server. " .
                "Available IPs: " . implode(', ', $allIps)
            );
        }

        $this->logger->info('Selected private IPs for NAT mapping', ['private_ips' => $privateIps]);

        $natMap = $this->parseNatMap();
        $results = [];

        foreach ($privateIps as $privateIp) {
            $publicIp = $this->resolvePublicIp($privateIp, $natMap);
            $this->logger->info('Mapped private IP to public IP', [
                'private_ip' => $privateIp,
                'public_ip' => $publicIp,
            ]);
            $results[] = new ServerIpResult($publicIp, $privateIp);
        }

        return $results;
    }

    /**
     * @param array<string, string> $natMap
     */
    private function resolvePublicIp(string $privateIp, array $natMap): string
    {
        if (!empty($natMap)) {
            if (isset($natMap[$privateIp])) {
                $publicIp = $natMap[$privateIp];
                if (!$this->isPublicIpv4($publicIp)) {
                    throw new \RuntimeException(
                        "NAT_MAP entry for $privateIp maps to '$publicIp' which is not a valid public IPv4 address."
                    );
                }
                $this->logger->debug('Resolved public IP from NAT_MAP', [
                    'private_ip' => $privateIp,
                    'public_ip' => $publicIp,
                ]);
                return $publicIp;
            }

            $this->logger->warning("NAT_MAP is set but has no entry for private IP $privateIp, falling back to external resolution.");
        }

        if ($this->publicIpResolver === null) {
            throw new \RuntimeException(
                "Cannot resolve public IP for $privateIp: NAT_MAP is not set and no PublicIpResolver is available."
            );
        }

        $this->logger->info('Resolving public IP for private IP via external service', [
            'private_ip' => $privateIp,
        ]);

        return $this->publicIpResolver->resolve($privateIp);
    }

    /**
     * @return string[]
     */
    private function parsePrivateNetwork(): array
    {
        return array_map('trim', explode(',', $this->privateNetwork));
    }

    /**
     * Parses NAT_MAP into an associative array of private IP => public IP.
     * Format: "10.0.1.5:203.0.113.10, 10.0.1.6:203.0.113.11"
     *
     * @return array<string, string>
     */
    private function parseNatMap(): array
    {
        if ($this->natMap === '') {
            return [];
        }

        $map = [];
        $entries = array_map('trim', explode(',', $this->natMap));

        foreach ($entries as $entry) {
            if ($entry === '') {
                continue;
            }
            $parts = explode(':', $entry, 2);
            if (count($parts) !== 2) {
                throw new \RuntimeException("Invalid NAT_MAP entry: '$entry'. Expected format: 'private_ip:public_ip'.");
            }
            $map[trim($parts[0])] = trim($parts[1]);
        }

        return $map;
    }

    private function isPublicIpv4(string $ip): bool
    {
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
            return []; // @codeCoverageIgnore
        }

        foreach ($interfaces as $interface) {
            if (!is_array($interface) || !isset($interface['up']) || $interface['up'] === false) {
                continue;
            }

            if (!isset($interface['unicast']) || !is_array($interface['unicast'])) {
                continue; // @codeCoverageIgnore
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
