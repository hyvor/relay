<?php

namespace App\Entity\Type;

enum DebugIncomingEmailType: string
{
    case BOUNCE = 'bounce';
    case COMPLAINT = 'complaint';
}
