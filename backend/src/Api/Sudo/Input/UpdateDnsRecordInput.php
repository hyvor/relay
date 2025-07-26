<?php

namespace App\Api\Sudo\Input;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateDnsRecordInput
{
    #[Assert\Length(max: 10)]
    public string $type {
        set {
            $this->typeSet = true;
            $this->type = $value;
        }
    }

    private(set) bool $typeSet;

    public string $subdomain {
        set {
            $this->subdomainSet = true;
            $this->subdomain = $value;
        }
    }

    private(set) bool $subdomainSet;

    public string $content {
        set {
            $this->contentSet = true;
            $this->content = $value;
        }
    }

    private(set) bool $contentSet;

    #[Assert\PositiveOrZero]
    public int $ttl {
        set {
            $this->ttlSet = true;
            $this->ttl = $value;
        }
    }

    private(set) bool $ttlSet;

    #[Assert\PositiveOrZero]
    public int $priority {
        set {
            $this->prioritySet = true;
            $this->priority = $value;
        }
    }

    private(set) bool $prioritySet;
}
