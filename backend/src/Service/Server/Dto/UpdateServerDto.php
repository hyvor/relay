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

    public int $apiWorkers {
        set {
            $this->apiWorkersSet = true;
            $this->apiWorkers = $value;
        }
    }

    public int $emailWorkers {
        set {
            $this->emailWorkersSet = true;
            $this->emailWorkers = $value;
        }
    }

    public int $webhookWorkers {
        set {
            $this->webhookWorkersSet = true;
            $this->webhookWorkers = $value;
        }
    }

    private(set) bool $lastPingAtSet = false;
    private(set) bool $privateIpSet = false;
    private(set) bool $apiWorkersSet = false;
    private(set) bool $emailWorkersSet = false;
    private(set) bool $webhookWorkersSet = false;

}