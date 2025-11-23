<?php

namespace App\Service\Idempotency\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class ClearExpiredIdempotencyRecordsMessage
{
}