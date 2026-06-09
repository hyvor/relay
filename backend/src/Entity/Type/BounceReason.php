<?php

namespace App\Entity\Type;

enum BounceReason: string
{
    case RECIPIENT = 'recipient';
    case INFRASTRUCTURE = 'infrastructure';
}
