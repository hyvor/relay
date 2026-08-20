<?php

namespace App\Service\Ip\ServerIpResolver;

readonly class ResolvedIp
{
    public function __construct(
        public string $publicIp,
        public ?string $privateIp = null,
    ) {}
}
