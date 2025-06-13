<?php

namespace App\Service\Server\Dto;

class UpdateServerDto
{

    public \DateTimeImmutable $lastPingAt {
        set {
            $this->lastPingAtSet = true;
            $this->lastPingAt = $value;
        }
    }

    private(set) bool $lastPingAtSet = false;

}