<?php

namespace App\Service\IncomingMail\Event;

use App\Entity\DebugIncomingEmail;
use App\Entity\Send;
use App\Entity\SendRecipient;

readonly class IncomingBounceEvent
{
    public function __construct(
        public Send $send,
        public SendRecipient $sendRecipient,
//        public DebugIncomingEmail $debugIncomingEmail,
    ) {
    }
}
