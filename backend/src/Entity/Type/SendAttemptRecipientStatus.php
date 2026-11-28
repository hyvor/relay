<?php

namespace App\Entity\Type;

/**
 * What the SMTP server returned for a recipient in a send attempt.
 * This does not change later (later bounces and complaints only update the SendRecipient).
 */
enum SendAttemptRecipientStatus: string
{
    case ACCEPTED = 'accepted';
    case DEFERRED = 'deferred';
    case BOUNCED = 'bounced';
    case FAILED = 'failed';
}
