<?php

namespace App\Service\Idempotency\Message;

readonly class ClearIdempotencyRecordsOfProjectMessage
{

    public function __construct(private int $projectId)
    {
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

}