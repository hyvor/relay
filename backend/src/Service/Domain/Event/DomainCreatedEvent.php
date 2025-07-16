<?php

namespace App\Service\Domain\Event;

use App\Entity\Domain;

class DomainCreatedEvent
{

    public function __construct(
        public Domain $domain
    )
    {
    }

}