<?php

namespace App\Service\Dns\Resolve;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves DNS over the JSON API of the endpoint set in DNS_OVER_HTTPS_URL.
 */
class DnsOverHttp implements DnsResolveInterface
{

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $dnsQueryUrl,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(string $domain, DnsType $dnsType): ResolveResult
    {
        $type = $dnsType->value;
        $url = $this->dnsQueryUrl . "?name=$domain&type=$type";

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
                'DNS over HTTPS failed: ' . $e->getMessage(),
                [
                    'url' => $url
                ]
            );

            throw new DnsResolvingFailedException($e->getMessage(), previous: $e);
        }
    }

}
