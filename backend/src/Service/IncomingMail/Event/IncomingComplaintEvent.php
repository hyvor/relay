<?php

namespace App\Service\IncomingMail\Event;

use App\Api\Console\Object\ComplaintObject;
use App\Entity\Send;
use App\Entity\SendRecipient;

readonly class IncomingComplaintEvent
{
    public function __construct(
        public Send $send,
        public SendRecipient $sendRecipient,
        public ComplaintObject $complaint,
    ) {
    }
}
