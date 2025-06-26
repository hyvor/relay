<?php

namespace App\Entity\Type;

enum WebhooksEventEnum: string
{
    case SEND_DELIVERED = 'send.delivered';
    case SEND_BOUNCED = 'send.bounced';
    case SEND_COMPLAINED = 'send.complained';
    case SUPPRESSION_CREATED = 'suppression.created';
    case SUPPRESSION_DELETED = 'suppression.deleted';
}
