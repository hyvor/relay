<?php

namespace App\Service\Ip\Dto;

use App\Entity\Queue;
use App\Util\OptionalPropertyTrait;

class UpdateIpAddressDto
{

    use OptionalPropertyTrait;

    public ?Queue $queue;
    public bool $isActive {
        set {
            $this->isActiveSet = true;
            $this->isActive = $value;
        }
    }

    private(set) bool $isActiveSet = false;

}
