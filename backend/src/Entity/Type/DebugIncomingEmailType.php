<?php

declare(strict_types=1);

namespace App\Entity\Type;

enum DebugIncomingEmailType: string
{
    case BOUNCE = 'bounce';
    case COMPLAINT = 'complaint';
}
