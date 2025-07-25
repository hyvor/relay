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

    public ?string $privateIp {
        set {
            $this->privateIpSet = true;
            $this->privateIp = $value;
        }
    }

    private(set) bool $lastPingAtSet = false;
    private(set) bool $privateIpSet = false;

}