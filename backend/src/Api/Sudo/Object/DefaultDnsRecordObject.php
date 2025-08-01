<?php

namespace App\Api\Sudo\Object;

use App\Entity\Type\DnsRecordType;
use App\Service\Management\GoState\GoStateDnsRecord;

class DefaultDnsRecordObject
{
    public DnsRecordType $type;
    public string $host;
    public string $content;
    public int $ttl;
    public int $priority;

    public function __construct(GoStateDnsRecord $dnsRecord)
    {
        $this->type = $dnsRecord->type;
        $this->host = $dnsRecord->host;
        $this->content = $dnsRecord->content;
        $this->ttl = $dnsRecord->ttl;
        $this->priority = $dnsRecord->priority;
    }
}