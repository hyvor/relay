<?php

namespace App\Tests\Service\Management\MessageHandler;

use App\Service\Management\Message\PingMessage;
use App\Service\Management\MessageHandler\PingMessageHandler;
use App\Service\Server\ServerService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PingMessage::class)]
#[CoversClass(PingMessageHandler::class)]
#[CoversClass(ServerService::class)]
class PingMessageHandlerTest extends KernelTestCase
{

    public function test_updates_last_ping(): void
    {
        $server = ServerFactory::createOne(['hostname' => 'hyvor-relay', 'lastPingAt' => null]);

        $message = new PingMessage();

        $transport = $this->transport('scheduler_server');
        $transport->send($message);
        $transport->throwExceptions()->process();

        $server->_refresh();
        $this->assertNotNull($server->getLastPingAt());
    }

    public function test_no_server_found(): void
    {
        $message = new PingMessage();

        $transport = $this->transport('scheduler_server');
        $transport->send($message);
        $transport->throwExceptions()->process();

        $this->addToAssertionCount(1); // If no exception is thrown, the test passes
    }

}