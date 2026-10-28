<?php

namespace App\Service\Send\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class RouteNullIpsMessage
{

    public function __construct(
        public int $queueId,
    ) {
    }

}
