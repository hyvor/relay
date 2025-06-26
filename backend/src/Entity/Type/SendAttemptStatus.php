<?php

namespace App\Entity\Type;

enum SendAttemptStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';
}
