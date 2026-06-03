<?php

namespace App\Entity\Type;

enum WarmupStatus: string
{
    case WARMING = 'warming';
    case WARMED = 'warmed';
}
