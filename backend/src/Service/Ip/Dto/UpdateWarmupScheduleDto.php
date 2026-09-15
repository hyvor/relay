<?php

namespace App\Service\Ip\Dto;

use App\Entity\Type\WarmupStatus;

class UpdateWarmupScheduleDto
{

    public ?WarmupStatus $status {
        set {
            $this->statusSet = true;
            $this->status = $value;
        }
    }

    private(set) bool $statusSet = false;

    /**
     * @var array<int>|null
     */
    public ?array $schedule {
        set {
            $this->scheduleSet = true;
            $this->schedule = $value;
        }
    }

    private(set) bool $scheduleSet = false;

}
