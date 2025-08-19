<?php

namespace App\Tests\MessageHandler\ServerTasks;

use App\Entity\ServerTask;
use App\Service\Idempotency\Message\ClearExpiredIdempotencyRecordsMessage;
use App\Service\Management\Message\ServerTaskMessage;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ServerTaskFactory;

class UpdateStateTest extends KernelTestCase
{
    public function test_update_state_using_server_tasks(): void
    {
        $serverTask = ServerTaskFactory::createOne();
        $serverTaskId = $serverTask->getId();

        $message = new ServerTaskMessage();

        $this->getMessageBus()->dispatch($message);

        $transport = $this->transport('scheduler_server');
        $transport->send($message);
        $transport->throwExceptions()->process();

        $serverTaskDb = $this->em->getRepository(ServerTask::class)->find($serverTaskId);
        $this->assertNull($serverTaskDb);
    }
}
