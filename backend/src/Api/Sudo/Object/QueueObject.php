<?php

namespace App\Api\Sudo\Object;

use App\Entity\Queue;

class QueueObject
{

    public int $id;
    public int $created_at;
    public int $updated_at;
    public string $type;
    public string $name;
    public string $full_name;
    public ?int $dedicated_organization_id;
    public bool $is_reserved;
    public ?int $projects_count;

    public function __construct(Queue $queue, ?int $projectsCount = null)
    {
        $this->id = $queue->getId();
        $this->created_at = $queue->getCreatedAt()->getTimestamp();
        $this->updated_at = $queue->getUpdatedAt()->getTimestamp();
        $this->type = $queue->getType()->value;
        $this->name = $queue->getName();
        $this->full_name = $queue->getFullName();
        $this->dedicated_organization_id = $queue->getDedicatedOrganizationId();
        $this->is_reserved = $queue->isReserved();
        $this->projects_count = $projectsCount;
    }

}
