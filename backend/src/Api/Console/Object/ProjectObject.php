<?php

namespace App\Api\Console\Object;

use App\Entity\Project;

class ProjectObject
{
    public int $id;
    public int $created_at; // unix timestamp
    public string $name;

    public function __construct(Project $project)
    {
        $this->id = $project->getId();
        $this->created_at = $project->getCreatedAt()->getTimestamp();
        $this->name = $project->getName();
    }
}