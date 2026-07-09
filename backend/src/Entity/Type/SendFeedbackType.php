<?php

declare(strict_types=1);

namespace App\Entity\Type;

enum SendFeedbackType: string
{
    case BOUNCE = 'bounce';
    case COMPLAINT = 'complaint';
}
