<?php

namespace App\Service\Dns\Resolve;

class DnsOverDns implements DnsResolveInterface
{

    public function resolve(string $domain, DnsType $dnsType): ResolveResult
    {
        // if needed, use dns_get_record() as a fallback for HTTP DNS resolving
        throw new \RuntimeException('DNS over DNS is not implemented yet.');
    }

}