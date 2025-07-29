<?php

namespace App\Api\Sudo\Input;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateInstanceInput
{

    public string $domain {
        set {
            $this->domainSet = true;
            $this->domain = $value;
        }
    }
    private(set) bool $domainSet = false;

    #[Assert\Cidr(
        version: Assert\Ip::V4,
        message: 'This value is not a valid CIDR notation for a private network.'
    )]
    public string $private_network_cidr {
        set {
            $this->private_network_cidrSet = true;
            $this->private_network_cidr = $value;
        }
    }
    private(set) bool $private_network_cidrSet = false;

}