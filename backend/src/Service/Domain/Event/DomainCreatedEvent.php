<?php

namespace App\Service\Domain\Event;

use App\Entity\Domain;

readonly class DomainCreatedEvent
{

    public function __construct(
        public Domain $domain
    ) {
    }

}