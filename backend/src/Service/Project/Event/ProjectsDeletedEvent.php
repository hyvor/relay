<?php

namespace App\Service\Project\Event;

use App\Entity\Project;

class ProjectsDeletedEvent
{
    /**
     * @param Project[] $projects
     */
    public function __construct(
        public array $projects,
    ) {
    }
}
