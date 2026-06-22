<?php

namespace App\Api\Sudo\Object;

use App\Entity\Project;

class ProjectObject
{
    public int $id;
    public int $user_id;
    public string $name;
    public int $created_at;
    public int $updated_at;
    public ?int $organization_id;
    public string $send_type;

    public function __construct(Project $project)
    {
        $this->id = $project->getId();
        $this->user_id = $project->getUserId();
        $this->name = $project->getName();
        $this->created_at = $project->getCreatedAt()->getTimestamp();
        $this->updated_at = $project->getUpdatedAt()->getTimestamp();
        $this->organization_id = $project->getOrganizationId();
        $this->send_type = $project->getSendType()->value;
    }
}
