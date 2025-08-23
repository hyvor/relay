<?php

namespace App\Entity\Type;

enum SendRecipientType: string
{

    case TO = 'to';
    case CC = 'cc';
    case BCC = 'bcc';

}