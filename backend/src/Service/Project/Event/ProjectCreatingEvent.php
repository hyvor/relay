<?php

declare(strict_types=1);

namespace App\Service\Project\Event;

class ProjectCreatingEvent
{
    public function __construct(
        public int $userId
    ) {
    }
}
