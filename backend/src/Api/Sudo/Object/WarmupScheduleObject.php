<?php

namespace App\Api\Sudo\Object;

use App\Entity\Type\WarmupStatus;
use App\Entity\WarmupSchedule;

class WarmupScheduleObject
{

    public int $id;
    public int $ip_address_id;
    public WarmupStatus $status;
    public int $started_date;
    public int $sent_today;
    public int $max_today;
    /** @var array<int> */
    public array $schedule;
    /** @var array<int> */
    public array $results;
    public int $created_at;
    public int $updated_at;

    public function __construct(WarmupSchedule $schedule)
    {
        $this->id = $schedule->getId();
        $this->ip_address_id = $schedule->getIpAddress()->getId();
        $this->status = $schedule->getStatus();
        $this->started_date = $schedule->getStartedDate()->getTimestamp();
        $this->sent_today = $schedule->getSentToday();
        $this->max_today = $schedule->getMaxToday();
        $this->schedule = $schedule->getSchedule();
        $this->results = $schedule->getResults();
        $this->created_at = $schedule->getCreatedAt()->getTimestamp();
        $this->updated_at = $schedule->getUpdatedAt()->getTimestamp();
    }

}
