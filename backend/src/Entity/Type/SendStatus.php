<?php

namespace App\Entity\Type;

/**
 * @deprecated
 */
enum SendStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case ACCEPTED = 'accepted';
    case BOUNCED = 'bounced';
    case COMPLAINED = 'complained';
}
