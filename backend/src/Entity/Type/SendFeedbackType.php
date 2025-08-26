<?php

namespace App\Entity\Type;

enum SendFeedbackType: string
{
    case BOUNCE = 'bounce';
    case COMPLAINT = 'complaint';
}

