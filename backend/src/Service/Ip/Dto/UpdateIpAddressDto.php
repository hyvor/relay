<?php

namespace App\Service\Ip\Dto;

use App\Entity\Queue;
use App\Entity\Type\WarmupStatus;

class UpdateIpAddressDto
{

    public ?Queue $queue {
        set {
            $this->queueSet = true;
            $this->queue = $value;
        }
    }

    private(set) bool $queueSet = false;

    public ?WarmupStatus $warmup_status {
        set {
            $this->warmupStatusSet = true;
            $this->warmup_status = $value;
        }
    }

    private(set) bool $warmupStatusSet = false;

    /**
     * @var array<int>|null
     */
    public ?array $warmup_schedule {
        set {
            $this->warmupScheduleSet = true;
            $this->warmup_schedule = $value;
        }
    }

    private(set) bool $warmupScheduleSet = false;

}
