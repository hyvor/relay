<?php

namespace App\Api\Sudo\Object;

use App\Entity\Queue;

class QueueObject
{

    public int $id;
    public int $created_at;
    public int $updated_at;
    public string $name;
    public int $ip_count;

    public function __construct(Queue $queue, int $ipCount = 0)
    {
        $this->id = $queue->getId();
        $this->created_at = $queue->getCreatedAt()->getTimestamp();
        $this->updated_at = $queue->getUpdatedAt()->getTimestamp();
        $this->name = $queue->getName();
        $this->ip_count = $ipCount;
    }

}
