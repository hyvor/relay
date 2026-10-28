<?php

namespace App\Service\Dns\Resolve;

use App\Service\Go\Exception\GoHttpCallException;
use App\Service\Go\GoHttpApi;
use Psr\Log\LoggerInterface;

/**
 * Resolves DNS by delegating to the Go worker's DNS-over-HTTPS wireformat client
 * (worker/doh.go)
 */
class DnsOverHttp implements DnsResolveInterface
{

    public function __construct(
        private GoHttpApi $goHttpApi,
        private LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(string $domain, DnsType $dnsType): ResolveResult
    {
        try {
            $data = $this->goHttpApi->resolveDns($domain, $dnsType);
        } catch (GoHttpCallException $e) {
            $this->logger->error(
                'DNS resolution via Go worker failed: ' . $e->getMessage(),
                [
                    'domain' => $domain,
                    'type' => $dnsType->value,
                ],
            );

            throw new DnsResolvingFailedException($e->getMessage(), previous: $e);
        }

        return ResolveResult::fromArray($data);
    }

}
