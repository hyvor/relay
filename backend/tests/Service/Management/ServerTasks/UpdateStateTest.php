<?php

namespace App\Tests\Service\Management\ServerTasks;

use App\Entity\ServerTask;
use App\Service\Management\Message\ServerTaskMessage;
use App\Service\Management\MessageHandler\ServerTaskMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ServerFactory;
use App\Tests\Factory\ServerTaskFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(ServerTaskMessage::class)]
#[CoversClass(ServerTask::class)]
#[CoversClass(ServerTaskMessageHandler::class)]
class UpdateStateTest extends KernelTestCase
{
    public function test_update_state_using_server_tasks(): void
    {
        $mockResponse = new JsonMockResponse();
        $this->container->set(HttpClientInterface::class, new MockHttpClient($mockResponse));

        $server = ServerFactory::createOne(
            [
                'hostname' => 'hyvor-relay'
            ]
        );

        $serverTask = ServerTaskFactory::createOne(
            [
                'server' => $server,
            ]
        );
        $serverTaskId = $serverTask->getId();

        $transport = $this->transport('scheduler_server');
        $message = new ServerTaskMessage();
        $transport->send($message);
        $transport->throwExceptions()->process();

        $serverTaskDb = $this->em->getRepository(ServerTask::class)->find($serverTaskId);
        $this->assertNull($serverTaskDb);
    }
}
