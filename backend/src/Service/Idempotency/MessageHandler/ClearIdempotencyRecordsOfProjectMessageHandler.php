<?php

namespace App\Service\Idempotency\MessageHandler;

use App\Service\Idempotency\Message\ClearIdempotencyRecordsOfProjectMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ClearIdempotencyRecordsOfProjectMessageHandler
{

    public function __invoke(ClearIdempotencyRecordsOfProjectMessage $message): void
    {

        $projectId = $message->getProjectId();



    }

}