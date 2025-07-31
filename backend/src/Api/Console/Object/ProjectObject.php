<?php

namespace App\Api\Console\Object;

use App\Entity\Project;
use App\Entity\Type\ProjectSendType;

class ProjectObject
{
    public int $id;
    public int $created_at; // unix timestamp
    public string $name;
    public ProjectSendType $send_type;

    public function __construct(Project $project)
    {
        $this->id = $project->getId();
        $this->created_at = $project->getCreatedAt()->getTimestamp();
        $this->name = $project->getName();
        $this->send_type = $project->getSendType();
    }
}