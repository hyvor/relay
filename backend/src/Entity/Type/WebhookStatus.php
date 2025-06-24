<?php

namespace App\Entity\Type;

enum WebhookStatus: string
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
}