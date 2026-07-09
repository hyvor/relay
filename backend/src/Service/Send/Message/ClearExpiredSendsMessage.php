<?php

declare(strict_types=1);

namespace App\Service\Send\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class ClearExpiredSendsMessage
{
}
