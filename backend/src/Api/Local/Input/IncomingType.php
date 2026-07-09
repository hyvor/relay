<?php

declare(strict_types=1);

namespace App\Api\Local\Input;

enum IncomingType: string
{
    case BOUNCE = 'bounce';
    case COMPLAINT = 'complaint';
}
