<?php

declare(strict_types=1);

namespace App\Service\Domain\Dto;

use App\Entity\Type\DomainStatus;

class UpdateDomainDto
{
    public string $domain {
        set {
            $this->domain = $value;
            $this->domainSet = true;
        }
    }

    public DomainStatus $status {
        set {
            $this->status = $value;
            $this->statusSet = true;
        }
    }


    public private(set) bool $domainSet = false;
    public private(set) bool $statusSet = false;

}
