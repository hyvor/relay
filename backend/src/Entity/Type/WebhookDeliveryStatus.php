<?php

declare(strict_types=1);

namespace App\Entity\Type;

enum WebhookDeliveryStatus: string
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
}
