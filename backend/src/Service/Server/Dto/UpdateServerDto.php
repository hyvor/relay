<?php

declare(strict_types=1);

namespace App\Service\Server\Dto;

class UpdateServerDto
{
    public \DateTimeImmutable $lastPingAt {
        set {
            $this->lastPingAtSet = true;
            $this->lastPingAt = $value;
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

    public int $incomingWorkers {
        set {
            $this->incomingWorkersSet = true;
            $this->incomingWorkers = $value;
        }
    }

    public private(set) bool $lastPingAtSet = false;
    public private(set) bool $apiWorkersSet = false;
    public private(set) bool $emailWorkersSet = false;
    public private(set) bool $webhookWorkersSet = false;
    public private(set) bool $incomingWorkersSet = false;

}
