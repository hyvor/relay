<?php

namespace App\Api\Local\Input;

enum IncomingType: string
{
    case BOUNCE = 'bounce';
    case COMPLAINT = 'complaint';
}