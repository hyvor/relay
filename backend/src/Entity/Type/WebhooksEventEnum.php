<?php

namespace App\Entity\Type;

enum WebhooksEventEnum: string
{

    case SEND_ACCEPTED = 'send.accepted';
    case SEND_DEFERRED = 'send.deferred';
    case SEND_BOUNCED = 'send.bounced';
    case SEND_COMPLAINED = 'send.complained';

    // Created by Workers
    case SUPPRESSION_CREATED = 'suppression.created';
    case SUPPRESSION_DELETED = 'suppression.deleted';

    // Created by API
    case DOMAIN_CREATED = 'domain.created';
    case DOMAIN_STATUS_CHANGED = 'domain.status.changed';
    case DOMAIN_DELETED = 'domain.deleted';
}
