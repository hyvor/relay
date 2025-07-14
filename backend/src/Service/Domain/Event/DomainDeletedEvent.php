<?php

namespace App\Service\Domain\Event;

use App\Entity\Domain;

class DomainDeletedEvent
{

    public function __construct(
        public Domain $domain
    )
    {
    }

}