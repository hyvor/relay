<?php

namespace App\Service\Domain\Event;

use App\Entity\Domain;
use App\Service\Domain\DkimVerificationResult;

readonly class DomainStatusChangedEvent
{

    public function __construct(
        public Domain $domain,
        public DkimVerificationResult $result,
    ) {
    }

}