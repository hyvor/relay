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

    public function __construct(IpAddress $ipAddress, Instance $instance)
    {
        $this->id = $ipAddress->getId();
        $this->created_at = $ipAddress->getCreatedAt()->getTimestamp();
        $this->server_id = $ipAddress->getServer()->getId();
        $this->ip_address = $ipAddress->getIpAddress();
        $this->ptr = Ptr::getPtrDomain($ipAddress, $instance->getDomain());
        $queue = $ipAddress->getQueue();
        $this->queue = $queue ? new QueueObject($queue) : null;
        $this->is_ptr_forward_valid = $ipAddress->getIsPtrForwardValid();
        $this->is_ptr_reverse_valid = $ipAddress->getIsPtrReverseValid();
    }

}