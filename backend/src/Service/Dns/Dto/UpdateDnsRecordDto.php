<?php

declare(strict_types=1);

namespace App\Service\Dns\Dto;

use App\Entity\Type\DnsRecordType;

class UpdateDnsRecordDto
{
    public DnsRecordType $type {
        set {
            $this->typeSet = true;
            $this->type = $value;
        }
    }

    public private(set) bool $typeSet;

    public string $subdomain {
        set {
            $this->subdomainSet = true;
            $this->subdomain = $value;
        }
    }

    public private(set) bool $subdomainSet;

    public string $content {
        set {
            $this->contentSet = true;
            $this->content = $value;
        }
    }

    public private(set) bool $contentSet;

    public int $ttl {
        set {
            $this->ttlSet = true;
            $this->ttl = $value;
        }
    }

    public private(set) bool $ttlSet;

    public int $priority {
        set {
            $this->prioritySet = true;
            $this->priority = $value;
        }
    }

    public private(set) bool $prioritySet;
}
