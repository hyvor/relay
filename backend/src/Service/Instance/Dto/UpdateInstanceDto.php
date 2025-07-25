<?php

namespace App\Service\Instance\Dto;

class UpdateInstanceDto
{

    public string $domain {
        set {
            $this->domainSet = true;
            $this->domain = $value;
        }
    }

    private(set) bool $domainSet;

}