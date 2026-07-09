<?php

declare(strict_types=1);

namespace App\Service\Idempotency\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class ClearExpiredIdempotencyRecordsMessage
{
}
