<?php

namespace App\Api\Sudo\Object;

use App\Entity\DnsRecord;
use App\Entity\Type\DnsRecordType;

class DnsRecordObject
{
    public int $id;
    public int $created_at;
    public int $updated_at;
    public DnsRecordType $type;
    public string $subdomain;
    public string $content;
    public int $ttl;
    public int $priority;

    public function __construct(DnsRecord $dnsRecord)
    {
        $this->id = $dnsRecord->getId();
        $this->created_at = $dnsRecord->getCreatedAt()->getTimestamp();
        $this->updated_at = $dnsRecord->getUpdatedAt()->getTimestamp();
        $this->type = $dnsRecord->getType();
        $this->subdomain = $dnsRecord->getSubdomain();
        $this->content = $dnsRecord->getContent();
        $this->ttl = $dnsRecord->getTtl();
        $this->priority = $dnsRecord->getPriority();
    }
}
