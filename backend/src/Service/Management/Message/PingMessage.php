<?php

namespace App\Service\Management\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('default')]
class PingMessage
{

    public function __construct()
    {}

}