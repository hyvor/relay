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

    private(set) bool $domainSet;

}