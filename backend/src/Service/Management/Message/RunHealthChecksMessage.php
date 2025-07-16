<?php

namespace App\Service\Management\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('default')]
class RunHealthChecksMessage
{
    public function __construct()
    {
    }
} 