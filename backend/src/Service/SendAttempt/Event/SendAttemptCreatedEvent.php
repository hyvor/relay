<?php

namespace App\Service\SendAttempt\Event;

use App\Entity\SendAttempt;

readonly class SendAttemptCreatedEvent
{

    public function __construct(
        public SendAttempt $sendAttempt,
    ) {
    }

}
