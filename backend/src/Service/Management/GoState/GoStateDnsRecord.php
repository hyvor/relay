<?php

namespace App\Service\Management\GoState;

use App\Entity\Type\DnsRecordType;

class GoStateDnsRecord
{

    public function __construct(
        public DnsRecordType $type,
        public string $host,
        public string $content,
        public int $ttl = 300,
        public int $priority = 0,
    )
    {
    }

}