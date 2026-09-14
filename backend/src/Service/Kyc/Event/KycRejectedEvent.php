<?php

namespace App\Service\Kyc\Event;

use App\Entity\Kyc;

readonly class KycRejectedEvent
{
    public function __construct(
        public Kyc $kyc
    ) {
    }

}
