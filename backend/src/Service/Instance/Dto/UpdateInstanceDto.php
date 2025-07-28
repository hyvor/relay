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

    private(set) bool $domainSet = false;


    public string $privateNetworkCidr {
        set {
            $this->privateNetworkCidrSet = true;
            $this->privateNetworkCidr = $value;
        }
    }

    private(set) bool $privateNetworkCidrSet = false;
}