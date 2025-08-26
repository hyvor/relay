<?php

namespace App\Entity\Type;

enum SuppressionReason: string
{
    case BOUNCE = 'bounce';

    case COMPLAINT = 'complaint';
}
