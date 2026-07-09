<?php

declare(strict_types=1);

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
    public private(set) bool $domainSet = false;
}
