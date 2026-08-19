<?php

namespace App\Service\Ip\Event;

use App\Entity\IpAddress;

readonly class IpRemovedEvent
{

    public function __construct(
        private IpAddress $ipAddress,
    ) {
    }

    public function getIpAddress(): IpAddress
    {
        return $this->ipAddress;
    }

}
