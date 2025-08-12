<?php

namespace App\Service\Blacklist;

use App\Service\Blacklist\Exception\DnsblLookupException;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\DnsType;

class DnsblQuery
{

    public function __construct(
        private DnsResolveInterface $dnsResolve
    ) {
    }

    /**
     * @throws DnsblLookupException
     */
    public function query(IpBlacklist $blacklist, string $ip): DnsblQueryResult
    {
        $reversedIp = implode('.', array_reverse(explode('.', $ip)));
        $query = $reversedIp . '.' . $blacklist->getDnsLookupDomain();

        try {
            $result = $this->dnsResolve->resolve($query, DnsType::A);
        } catch (DnsResolvingFailedException $e) {
            throw new DnsblLookupException(
                $blacklist->getName(),
                $query,
                $ip,
                $e->getMessage(),
            );
        }

        //  having a record = IP blocked
        if ($result->ok()) {
            return new DnsblQueryResult(
                blocked: true,
                resolvedIp: $result->answers[0]->data ?? ''
            );
        }

        // NXDOMAIN = IP not blocked
        if ($result->status === 3) {
            return new DnsblQueryResult(
                blocked: false,
            );
        }

        // Any other status indicates an error
        throw new DnsblLookupException(
            $blacklist->getName(),
            $query,
            $ip,
            'DNS lookup returned an error: ' . $result->error()
        );
    }

}