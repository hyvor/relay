<?php

namespace App\Entity\Type;

enum SendAttemptStatus: string
{
    case ACCEPTED = 'accepted';
    case DEFERRED = 'deferred';
    case BOUNCED = 'bounced';
    // host accepted but deferred or bounced for some recipients (in the RCPT phase)
    case PARTIAL = 'partial';
    // failed to connect or send at all
    case FAILED = 'failed';
}
