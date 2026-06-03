<?php

namespace App\Api\Sudo\Object;

use App\Entity\Instance;
use App\Entity\IpAddress;
use App\Service\Ip\Ptr;

class IpAddressObject
{

    public int $id;
    public int $created_at;
    public int $server_id;
    public string $ip_address;
    public string $ptr;
    public ?QueueObject $queue = null;
    public bool $is_ptr_forward_valid = false;
    public bool $is_ptr_reverse_valid = false;
    public string $warmup_status;
    public ?int $warmup_started_date;
    public int $warmup_sent_today;
    public int $warmup_max_today;
    /** @var array<int>|null */
    public ?array $warmup_schedule;
    public bool $is_warming_up;

    public function __construct(IpAddress $ipAddress, string $instanceDomain)
    {
        $this->id = $ipAddress->getId();
        $this->created_at = $ipAddress->getCreatedAt()->getTimestamp();
        $this->server_id = $ipAddress->getServer()->getId();
        $this->ip_address = $ipAddress->getIpAddress();
        $this->ptr = Ptr::getPtrDomain($ipAddress, $instanceDomain);
        $queue = $ipAddress->getQueue();
        $this->queue = $queue ? new QueueObject($queue) : null;
        $this->is_ptr_forward_valid = $ipAddress->getIsPtrForwardValid();
        $this->is_ptr_reverse_valid = $ipAddress->getIsPtrReverseValid();
        $this->warmup_status = $ipAddress->getWarmupStatus()->value;
        $startedDate = $ipAddress->getWarmupStartedDate();
        $this->warmup_started_date = $startedDate ? $startedDate->getTimestamp() : null;
        $this->warmup_sent_today = $ipAddress->getWarmupSentToday();
        $this->warmup_max_today = $ipAddress->getWarmupMaxToday();
        $this->warmup_schedule = $ipAddress->getWarmupSchedule();
        $this->is_warming_up = $ipAddress->isWarmingUp();
    }

}