<?php

namespace App\Api\Console\Object;

use App\Entity\Suppression;

class SuppressionObject
{
    public int $id;
    public int $created_at;
    public string $email;
    public string $reason;
    public ?string $description;

    public function __construct(Suppression $suppression)
    {
        $this->id = $suppression->getId();
        $this->created_at = $suppression->getCreatedAt()->getTimestamp();
        $this->email = $suppression->getEmail();
        $this->reason = $suppression->getReason()->value;
        $this->description = $suppression->getDescription();
    }
}
