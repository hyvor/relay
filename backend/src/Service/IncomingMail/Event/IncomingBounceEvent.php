<?php

namespace App\Service\IncomingMail\Event;

use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Service\IncomingMail\Dto\BounceDto;

readonly class IncomingBounceEvent
{
    public function __construct(
        public Send $send,
        public SendRecipient $sendRecipient,
        public BounceDto $bounce,
    ) {
    }
}
