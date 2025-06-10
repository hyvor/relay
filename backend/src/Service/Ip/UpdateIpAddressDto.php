<?php

namespace App\Service\Ip;

class UpdateIpAddressDto
{

    public bool $isActive {
        set {
            $this->isActiveSet = true;
            $this->isActive = $value;
        }
    }

    private(set) bool $isActiveSet = false;

}