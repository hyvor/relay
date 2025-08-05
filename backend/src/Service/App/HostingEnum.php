<?php

namespace App\Service\App;

enum HostingEnum: string
{

    case SELF = 'self'; // self-hosted
    case CLOUD = 'cloud'; // our cloud

    public function isCloud(): bool
    {
        return $this === self::CLOUD;
    }

}
