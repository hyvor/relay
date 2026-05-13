<?php

namespace App\Service\Send\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
readonly class RouteNullIpsMessage
{

    public function __construct(
        public int $queueId,
    ) {
    }

}
