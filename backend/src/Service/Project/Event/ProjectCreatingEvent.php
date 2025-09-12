<?php

namespace App\Service\Project\Event;

class ProjectCreatingEvent
{
    public function __construct(
        public int $userId
    ) {
    }
}
