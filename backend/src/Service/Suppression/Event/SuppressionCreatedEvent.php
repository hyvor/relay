<?php

namespace App\Service\Suppression\Event;

use App\Entity\Suppression;

readonly class SuppressionCreatedEvent
{
    public function __construct(
        public Suppression $suppression
    ) {
    }

}
