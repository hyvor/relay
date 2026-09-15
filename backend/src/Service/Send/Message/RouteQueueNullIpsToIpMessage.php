<?php

namespace App\Service\Send\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class RouteQueueNullIpsToIpMessage
{

    public function __construct(
        public int $queueId,
        public int $ipAddressId,
    ) {
    }

}
