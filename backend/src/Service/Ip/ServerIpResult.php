<?php

namespace App\Service\Ip;

class ServerIpResult
{
    public function __construct(
        public readonly string $publicIp,
        public readonly ?string $privateIp = null,
    ) {
    }
}
