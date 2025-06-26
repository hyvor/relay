<?php

namespace App\Entity\Type;

enum WebhookDeliveryStatus: string
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
}