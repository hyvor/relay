<?php

namespace App\Service\Dns\Resolve;

enum DnsType: string
{

    case A = 'A';
    case AAAA = 'AAAA';
    case MX = 'MX';
    case TXT = 'TXT';

}