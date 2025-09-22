<?php

namespace App\Service\Send\Event;

use App\Entity\SendRecipient;

readonly class SuppressedRecipientCreatedEvent
{
    public function __construct(
        public SendRecipient $sendRecipient,
    ) {
    }

}