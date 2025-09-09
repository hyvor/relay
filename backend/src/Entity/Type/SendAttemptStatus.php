<?php

namespace App\Entity\Type;

enum SendAttemptStatus: string
{
    case ACCEPTED = 'accepted';
    case DEFERRED = 'deferred';
    case BOUNCED = 'bounced';
    case FAILED = 'failed';
}
