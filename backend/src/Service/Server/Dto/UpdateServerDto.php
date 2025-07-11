<?php

namespace App\Service\Server\Dto;

use App\Util\OptionalPropertyTrait;

class UpdateServerDto
{
    use OptionalPropertyTrait;
    public \DateTimeImmutable $lastPingAt {
        set {
            $this->lastPingAtSet = true;
            $this->lastPingAt = $value;
        }
    }

    private(set) bool $lastPingAtSet = false;

    public int $apiWorkers {
        set {
            $this->apiWorkersSet = true;
            $this->apiWorkers = $value;
        }
    }

    private(set) bool $apiWorkersSet = false;

    public int $emailWorkers {
        set {
            $this->emailWorkersSet = true;
            $this->emailWorkers = $value;
        }
    }

    private(set) bool $emailWorkersSet = false;

    public int $webhookWorkers {
        set {
            $this->webhookWorkersSet = true;
            $this->webhookWorkers = $value;
        }
    }

    private(set) bool $webhookWorkersSet = false;

}
