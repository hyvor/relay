<?php

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


    private(set) bool $domainSet = false;
    private(set) bool $statusSet = false;

}