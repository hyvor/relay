<?php

declare(strict_types=1);

namespace App\Entity\Type;

enum SendRecipientType: string
{
    case TO = 'to';
    case CC = 'cc';
    case BCC = 'bcc';

}
