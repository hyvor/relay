<?php

declare(strict_types=1);

namespace App\Service\Dns\Resolve;

enum DnsType: string
{
    case A = 'A';
    case AAAA = 'AAAA';
    case MX = 'MX';
    case TXT = 'TXT';
    case PTR = 'PTR';

}
