<?php

namespace App\Api\Sudo\Object;

use App\Entity\Project;

class SendProjectSummaryObject
{
    public int $id;
    public string $name;

    public function __construct(Project $project)
    {
        $this->id = $project->getId();
        $this->name = $project->getName();
    }
}
