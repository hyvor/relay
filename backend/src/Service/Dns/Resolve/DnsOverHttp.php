<?php

namespace App\Service\Dns\Resolve;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls DNS over Cloudflare's HTTP JSON API.
 */
class DnsOverHttp implements DnsResolveInterface
{

    public const CLOUDFLARE_DNS_QUERY_URL = "https://cloudflare-dns.com/dns-query";

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(string $domain, DnsType $dnsType): ResolveResult
    {

        $type = $dnsType->value;
        $url = self::CLOUDFLARE_DNS_QUERY_URL . "?name=$domain&type=$type";

        try {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'headers' => [
                        'Accept' => 'application/dns-json'
                    ]
                ]
            );

            $data = $response->toArray();

            return ResolveResult::fromArray($data);

        } catch (ExceptionInterface $e) {

            $this->logger->error(
                'Failed to Cloudflare DNS: ' . $e->getMessage(),
                [
                    'url' => $url
                ]
            );

            throw new DnsResolvingFailedException($e->getMessage(), previous: $e);

        }

    }

}