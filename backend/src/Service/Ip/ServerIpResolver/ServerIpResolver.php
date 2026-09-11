<?php

namespace App\Service\Ip\ServerIpResolver;

use App\Service\App\Config;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\IpUtils;

class ServerIpResolver
{

    /**
     * @param callable $netGetInterfacesFunction
     */
    public function __construct(
        private ?PublicIpResolver $publicIpResolver = null,
        private LoggerInterface $logger = new NullLogger(),
        private $netGetInterfacesFunction = 'net_get_interfaces',
        private Config $appConfig,
    ) {}

    /**
     * Resolves IP addresses of the server.
     *
     * If PRIVATE_NETWORK env is set, returns private IPs in those ranges,
     * public IP automatically resolved via NAT_MAP or external service.
     * Otherwise, returns public IPv4 addresses directly.
     *
     * Important: this method depends on external services when PRIVATE_NETWORK is set
     * and NAT_MAP is not provided.
     *
     * @return ResolvedIp[]
     */
    public function resolveIps(): array
    {
        return $this->appConfig->getPrivateNetwork() ?
            $this->resolveIpsBehindNat() :
            $this->resolveIpsDirect();
    }

    /**
     * @return ResolvedIp[]
     */
    private function resolveIpsDirect(): array
    {
        $allIps = $this->getAllIpAddressesOnServer();
        $publicIps = [];
        foreach ($allIps as $ip) {
            if ($this->isPublicIpv4($ip)) {
                $publicIps[] = new ResolvedIp($ip);
            }
        }

        $this->logger->info(
            'Resolved public IPs directly',
            ['public_ips' => array_map(fn($ip) => $ip->getPublicIp(), $publicIps)],
        );

        return $publicIps;
    }

    private function resolveIpsBehindNat(): array
    {
        $privateNetwork = $this->appConfig->getPrivateNetwork();
        $privateRanges = array_map('trim', explode(',', $privateNetwork));

        $this->logger->info('NAT mode: scanning for private IPs in configured ranges', [
            'private_network' => $privateNetwork,
        ]);

        $allIps = $this->getAllIpAddressesOnServer();
        $privateIps = $this->filterIpv4InRanges($allIps, $privateRanges);

        if (empty($privateIps)) {
            $this->logger->warning(
                'NAT mode is enabled but no matching private IPv4 addresses were found on this server.',
                ['private_network' => $privateNetwork, 'available_ips' => $allIps],
            );
        }

        $this->logger->info('Selected private IPs for NAT mapping', ['private_ips' => $privateIps]);

        $natMap = $this->parseNatMap();
        $results = [];

        foreach ($privateIps as $privateIp) {
            $publicIp = $natMap[$privateIp] ?? $this->publicIpResolver->resolve($privateIp);

            $this->logger->info('Mapped private IP to public IP', [
                'private_ip' => $privateIp,
                'public_ip' => $publicIp,
                'method' => isset($natMap[$privateIp]) ? 'NAT Map' : 'External Resolver',
            ]);

            $results[] = new ResolvedIp($publicIp, $privateIp);
        }

        return $results;
    }

    /**
     * Parses NAT_MAP into an associative array of private IP => public IP.
     * Format: "10.0.1.5=203.0.113.10, 10.0.1.6=203.0.113.11"
     *
     * @return array<string, string>
     */
    private function parseNatMap(): array
    {
        $natMap = $this->appConfig->getNatMap();
        if (empty($natMap)) {
            return [];
        }

        $map = [];
        $entries = array_map('trim', explode(',', $natMap));

        foreach ($entries as $entry) {
            if (empty($entry)) {
                continue;
            }
            $parts = explode('=', $entry, 2);
            if (count($parts) !== 2) {
                $this->logger->error(
                    'Invalid NAT_MAP entry, expected format "private_ip=public_ip"',
                    ['entry' => $entry],
                );
                continue;
            }

            $privateIp = trim($parts[0]);
            $publicIp = trim($parts[1]);

            if (!$this->isPublicIpv4($publicIp)) {
                $this->logger->error(
                    'Invalid NAT_MAP entry, given public IP is not a valid public IPv4 address',
                    ['entry' => $entry, 'public_ip' => $publicIp],
                );
                continue;
            }

            if (!$this->isPrivateIp($privateIp)) {
                $this->logger->error(
                    'Invalid NAT_MAP entry, given private IP is not a valid private IPv4 address',
                    ['entry' => $entry, 'private_ip' => $privateIp],
                );
                continue;
            }

            $map[$privateIp] = $publicIp;
        }

        return $map;
    }

    private function isPublicIpv4(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $privateRanges = IpUtils::PRIVATE_SUBNETS;

        return !IpUtils::checkIp($ip, $privateRanges);
    }

    private function isPrivateIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $privateRanges = IpUtils::PRIVATE_SUBNETS;

        return IpUtils::checkIp($ip, $privateRanges);
    }

    /**
     * Filters a list of IPs down to those that are valid IPv4 addresses within the given CIDR ranges.
     *
     * @param string[] $ips
     * @param string[] $ranges
     * @return string[]
     */
    private function filterIpv4InRanges(array $ips, array $ranges): array
    {
        return array_values(
            array_filter(
                $ips,
                fn(string $ip)
                    => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && IpUtils::checkIp(
                        $ip,
                        $ranges,
                    ),
            ),
        );
    }

    /**
     * Gets all available IP addresses of the server.
     * @return string[]
     */
    private function getAllIpAddressesOnServer(): array
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
