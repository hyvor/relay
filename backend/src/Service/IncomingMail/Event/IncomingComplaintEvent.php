<?php

namespace App\Service\IncomingMail\Event;

use App\Entity\DebugIncomingEmail;
use App\Entity\Send;
use App\Entity\SendRecipient;

readonly class IncomingComplaintEvent
{
    public function __construct(
        public Send $send,
        public SendRecipient $sendRecipient,
//        public DebugIncomingEmail $debugIncomingEmail,
    ) {
    }
}
