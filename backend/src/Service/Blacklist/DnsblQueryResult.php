<?php

namespace App\Service\Blacklist;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DnsblQueryResult
{

    public function __construct(
        private bool $blocked,
        private ?string $resolvedIp = null,
    ) {
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function getResolvedIp(): ?string
    {
        return $this->resolvedIp;
    }

}