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
    public bool $is_active = false;
    public bool $is_enabled = true;

    public function __construct(IpAddress $ipAddress, Instance $instance)
    {
        $this->id = $ipAddress->getId();
        $this->created_at = $ipAddress->getCreatedAt()->getTimestamp();
        $this->server_id = $ipAddress->getServerId();
        $this->ip_address = $ipAddress->getIpAddress();
        $this->ptr = Ptr::getPtrDomain($ipAddress, $instance->getDomain());
        $queue = $ipAddress->getQueue();
        $this->queue = $queue ? new QueueObject($queue) : null;
        $this->is_active = $ipAddress->getIsActive();
        $this->is_enabled = $ipAddress->getIsEnabled();
    }

}