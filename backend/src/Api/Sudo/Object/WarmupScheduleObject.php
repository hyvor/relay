<?php

namespace App\Api\Sudo\Object;

use App\Entity\Type\WarmupStatus;
use App\Entity\WarmupSchedule;

class WarmupScheduleObject
{

    public int $id;
    public WarmupStatus $warmup_status;
    public ?int $warmup_started_date;
    public int $warmup_sent_today;
    public int $warmup_max_today;
    /** @var array<int>|null */
    public ?array $warmup_schedule;
    public int $created_at;
    public bool $is_warming_up;

    public function __construct(WarmupSchedule $schedule)
    {
        $this->id = $schedule->getId();
        $this->warmup_status = $schedule->getWarmupStatus();
        $startedDate = $schedule->getWarmupStartedDate();
        $this->warmup_started_date = $startedDate ? $startedDate->getTimestamp() : null;
        $this->warmup_sent_today = $schedule->getWarmupSentToday();
        $this->warmup_max_today = $schedule->getWarmupMaxToday();
        $this->warmup_schedule = $schedule->getWarmupSchedule();
        $this->created_at = $schedule->getCreatedAt()->getTimestamp();
        $this->is_warming_up = $schedule->isWarmingUp();
    }

}
