<?php

namespace App\Entity\Type;

enum SendStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';
}
