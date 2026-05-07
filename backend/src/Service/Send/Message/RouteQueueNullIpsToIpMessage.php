<?php

namespace App\Service\Send\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
readonly class RouteQueueNullIpsToIpMessage
{

    public function __construct(
        public int $queueId,
        public int $ipAddressId,
    ) {
    }

}
