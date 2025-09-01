<?php

namespace App\Entity\Type;

enum WebhooksEventEnum: string
{

    case SEND_RECIPIENT_ACCEPTED = 'send.recipient.accepted';
    case SEND_RECIPIENT_DEFERRED = 'send.recipient.deferred';
    case SEND_RECIPIENT_BOUNCED = 'send.recipient.bounced';
    case SEND_RECIPIENT_COMPLAINED = 'send.recipient.complained';
    case SEND_RECIPIENT_FAILED = 'send.recipient.failed';

    // Created by Workers
    case SUPPRESSION_CREATED = 'suppression.created';
    case SUPPRESSION_DELETED = 'suppression.deleted';

    // Created by API
    case DOMAIN_CREATED = 'domain.created';
    case DOMAIN_STATUS_CHANGED = 'domain.status.changed';
    case DOMAIN_DELETED = 'domain.deleted';
}
