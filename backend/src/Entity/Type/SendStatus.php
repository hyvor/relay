<?php

namespace App\Entity\Type;

enum SendStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case ACCEPTED = 'accepted';
    case BOUNCED = 'bounced';
    case COMPLAINED = 'complained';
}
