<?php

namespace App\Service\Kyc\Event;

use App\Entity\Kyc;

readonly class KycApprovedEvent
{
    public function __construct(
        public Kyc $kyc
    ) {
    }

}
