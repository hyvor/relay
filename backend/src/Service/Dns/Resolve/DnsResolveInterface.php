<?php

namespace App\Service\Dns\Resolve;

interface DnsResolveInterface
{

    /**
     * @throws DnsResolvingFailedException
     */
    public function resolve(string $domain, DnsType $dnsType): ResolveResult;

}