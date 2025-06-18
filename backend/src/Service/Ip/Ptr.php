<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;

class Ptr
{
    private const string PTR_PREFIX = 'smtp';

    public static function getPtrDomain(IpAddress $ipAddress, string $instanceDomain): string
    {
        return self::PTR_PREFIX . $ipAddress->getId() . '.' . $instanceDomain;
    }
}