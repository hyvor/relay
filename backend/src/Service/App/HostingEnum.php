<?php

declare(strict_types=1);

namespace App\Service\App;

enum HostingEnum: string
{
    case SELF = 'self'; // self-hosted
    case CLOUD = 'cloud'; // our cloud

}
