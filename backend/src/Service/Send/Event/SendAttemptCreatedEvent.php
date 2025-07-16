<?php

namespace App\Service\Send\Event;

use App\Entity\SendAttempt;

readonly class SendAttemptCreatedEvent
{

    public function __construct(
        public SendAttempt $sendAttempt,
    )
    {
    }

}