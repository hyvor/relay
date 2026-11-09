<?php

namespace App\Service\Ip\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('scheduler_server')]
readonly class CheckNatPrivateIpsMessage
{
}
