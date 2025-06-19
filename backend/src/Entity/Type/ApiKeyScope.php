<?php

namespace App\Entity\Type;

enum ApiKeyScope: string
{
    case FULL = 'full';
    case SEND_EMAIL = 'send_email';
}