<?php

namespace App\Tests\Service\Management\MessageHandler;

use App\Entity\ServerTask;
use App\Service\App\Process\ProcessFactory;
use App\Service\Go\GoHttpApi;
use App\Service\Management\Message\ServerTaskMessage;
use App\Service\Management\MessageHandler\ServerTaskMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ServerFactory;
use App\Tests\Factory\ServerTaskFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(ServerTaskMessage::class)]
#[CoversClass(ServerTaskMessageHandler::class)]
#[CoversClass(GoHttpApi::class)]
class ServerTaskMessageHandlerTest extends KernelTestCase
{

    public function test_when_no_server_found(): void
    {
        $transport = $this->transport('scheduler_server');
        $message = new ServerTaskMessage();
        $transport->send($message);
        $transport->throwExceptions()->process();
    }

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
                'payload' => ['api_workers_updated' => false]
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

    public function test_api_workers_updated(): void
    {
        $logger = $this->getTestLogger();

        $mockProcessFactory = $this->createMock(ProcessFactory::class);
        $mockProcessFactory->method('create')
            ->with(['supervisorctl', 'restart', 'frankenphp'])
            ->willReturn(new Process(['pwd']));
        $this->container->set(ProcessFactory::class, $mockProcessFactory);

        $mockResponse = new JsonMockResponse();
        $this->container->set(HttpClientInterface::class, new MockHttpClient($mockResponse));

        $server = ServerFactory::createOne(['hostname' => 'hyvor-relay']);

        $serverTask = ServerTaskFactory::createOne([
            'server' => $server,
            'payload' => ['api_workers_updated' => true]
        ]);
        $serverTaskId = $serverTask->getId();

        $transport = $this->transport('scheduler_server');
        $message = new ServerTaskMessage();
        $transport->send($message);
        $transport->throwExceptions()->process();

        $serverTaskDb = $this->em->getRepository(ServerTask::class)->find($serverTaskId);
        $this->assertNull($serverTaskDb);

        $this->assertTrue($logger->hasInfoThatContains('FrankenPHP restarting after API workers update'));
        $this->assertTrue($logger->hasInfoThatContains('FrankenPHP workers restarted: /app/backend'));
        $this->assertFalse($logger->hasErrorThatContains('Error output from restarting FrankenPHP workers'));
    }

}
