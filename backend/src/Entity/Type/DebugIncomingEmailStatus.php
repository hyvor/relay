<?php

namespace App\Entity\Type;

enum DebugIncomingEmailStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
