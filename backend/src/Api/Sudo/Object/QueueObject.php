<?php

namespace App\Api\Sudo\Object;

use App\Entity\Queue;

class QueueObject
{

    public int $id;
    public int $created_at;
    public int $updated_at;
    public string $name;

    public function __construct(Queue $queue)
    {
        $this->id = $queue->getId();
        $this->created_at = $queue->getCreatedAt()->getTimestamp();
        $this->updated_at = $queue->getUpdatedAt()->getTimestamp();
        $this->name = $queue->getName();
    }

}
