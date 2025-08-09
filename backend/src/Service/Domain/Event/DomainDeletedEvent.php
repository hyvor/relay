<?php

namespace App\Service\Domain\Event;

use App\Entity\Domain;

readonly class DomainDeletedEvent
{

    public function __construct(
        public Domain $domain
    ) {
    }

}