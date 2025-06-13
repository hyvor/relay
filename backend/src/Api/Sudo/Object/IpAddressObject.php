<?php

namespace App\Api\Sudo\Object;

use App\Entity\IpAddress;

class IpAddressObject
{

    public int $id;
    public int $created_at;
    public int $server_id;
    public string $ip_address;
    public ?string $email_queue = null;
    public bool $is_active = false;
    public bool $is_enabled = true;

    public function __construct(IpAddress $ipAddress)
    {
        $this->id = $ipAddress->getId();
        $this->created_at = $ipAddress->getCreatedAt()->getTimestamp();
        $this->server_id = $ipAddress->getServerId();
        $this->ip_address = $ipAddress->getIpAddress();
        $this->email_queue = $ipAddress->getEmailQueue();
        $this->is_active = $ipAddress->getIsActive();
        $this->is_enabled = $ipAddress->getIsEnabled();
    }

}