<?php

namespace App\Service\DnsRecord\Dto;

class CreateDnsRecordDto
{
    public function __construct(
        public readonly string $type,
        public readonly string $subdomain,
        public readonly string $content,
        public readonly int $ttl = 300,
        public readonly int $priority = 0,
    ) {}
}
