<?php

namespace App\Entity\Type;

enum DebugIncomingEmailsStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
