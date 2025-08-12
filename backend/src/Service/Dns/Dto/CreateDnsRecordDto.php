<?php

namespace App\Service\Dns\Dto;

use App\Entity\Type\DnsRecordType;

class CreateDnsRecordDto
{
    public function __construct(
        public readonly DnsRecordType $type,
        public readonly string $subdomain,
        public readonly string $content,
        public readonly int $ttl = 3600,
        public readonly int $priority = 0,
    ) {}
}
