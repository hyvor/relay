<?php

namespace App\Service\Ip\Event;

use App\Entity\IpAddress;
use App\Service\Ip\Dto\UpdateIpAddressDto;

readonly class IpAddressUpdatedEvent
{

    public function __construct(
        private IpAddress $ipAddressOld,
        private IpAddress $ipAddress,
        private UpdateIpAddressDto $updates
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getIpAddressOld(): IpAddress
    {
        return $this->ipAddressOld;
    }

    public function getIpAddress(): IpAddress
    {
        return $this->ipAddress;
    }

    public function getUpdates(): UpdateIpAddressDto
    {
        return $this->updates;
    }

}
