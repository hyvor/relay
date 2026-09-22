<?php

namespace App\Service\Ip\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class ResetIpWarmupMessage
{
}
