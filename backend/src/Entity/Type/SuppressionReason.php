<?php

namespace App\Entity\Type;

enum SuppressionReason: string
{
    case BOUNCE = 'bounce';

    case FBL = 'fbl';

    case COMPLAINT = 'complaint';
}
