<?php

namespace App\Service\Ip;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the public IP address of the server by calling external services.
 * Supports binding to a specific local IP address for NAT verification.
 */
class PublicIpResolver
{
    private const RESOLVERS = [
        'https://ifconfig.me/ip',
        'https://icanhazip.com',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Resolves the public IP address as seen from the outside.
     * When $localIp is provided, the HTTP request is bound to that interface,
     * which allows verifying the public IP associated with a specific private IP (NAT).
     *
     * @throws \RuntimeException if all resolvers fail
     */
    public function resolve(?string $localIp = null): string
    {
        $lastError = null;

        foreach (self::RESOLVERS as $url) {
            try {
                $options = ['timeout' => 10];
                if ($localIp !== null) {
                    $options['local_ip'] = $localIp;
                }

                $this->logger->debug('Resolving public IP from external service', [
                    'url' => $url,
                    'local_ip' => $localIp,
                ]);

                $response = $this->httpClient->request('GET', $url, $options);
                $ip = trim($response->getContent());

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $this->logger->info('Resolved public IP', [
                        'url' => $url,
                        'local_ip' => $localIp,
                        'public_ip' => $ip,
                    ]);
                    return $ip;
                }

                $this->logger->warning('External resolver returned an invalid IP', [
                    'url' => $url,
                    'response' => $ip,
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to reach external IP resolver', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                $lastError = $e;
            }
        }

        throw new \RuntimeException(
            'Failed to resolve public IP from all external services' . ($localIp ? " for local IP $localIp" : ''),
            0,
            $lastError
        );
    }
}
