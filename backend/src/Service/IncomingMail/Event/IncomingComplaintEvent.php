<?php

namespace App\Service\IncomingMail\Event;

use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Service\IncomingMail\Dto\ComplaintDto;

readonly class IncomingComplaintEvent
{
    public function __construct(
        public Send $send,
        public SendRecipient $sendRecipient,
        public ComplaintDto $complaint,
    ) {
    }
}
