<?php

namespace App\Entity\Type;

enum WebhooksEventEnum: string
{

    // Created by Workers
    case SEND_DELIVERED = 'send.delivered';
    case SEND_BOUNCED = 'send.bounced';
    case SEND_COMPLAINED = 'send.complained';

    // Created by Workers
    case SUPPRESSION_CREATED = 'suppression.created';
    case SUPPRESSION_DELETED = 'suppression.deleted';

    // Created by API
    case DOMAIN_CREATED = 'domain.created';
    case DOMAIN_VERIFIED = 'domain.verified';
    case DOMAIN_DELETED = 'domain.deleted';
}
