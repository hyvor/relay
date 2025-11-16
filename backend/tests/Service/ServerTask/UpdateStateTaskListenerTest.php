<?php

namespace App\Tests\Service\ServerTask;

use App\Repository\ServerTaskRepository;
use App\Service\Ip\Dto\UpdateIpAddressDto;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Server\Dto\UpdateServerDto;
use App\Service\Server\Event\ServerUpdatedEvent;
use App\Service\ServerTask\ServerTaskService;
use App\Service\ServerTask\UpdateStateTaskListener;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateStateTaskListener::class)]
#[CoversClass(ServerTaskService::class)]
#[CoversClass(ServerUpdatedEvent::class)]
#[CoversClass(IpAddressUpdatedEvent::class)]
class UpdateStateTaskListenerTest extends KernelTestCase
{

    public function test_no_task_when_server_updated_without_create_task_flag(): void
    {
        $server = ServerFactory::createOne();

        $updates = new UpdateServerDto();

        $event = new ServerUpdatedEvent(
            $server,
            $server,
            updates: $updates,
            createUpdateStateTask: false
        );

        $this->ed->dispatch($event);

        $serverTasks = $this->getService(ServerTaskRepository::class)->findAll();
        $this->assertCount(0, $serverTasks);
    }

    public function test_server_updated_server(): void
    {
        $server = ServerFactory::createOne();

        $updates = new UpdateServerDto();
        $updates->apiWorkers = 4;

        $event = new ServerUpdatedEvent(
            $server->_real(),
            $server->_real(),
            updates: $updates,
            createUpdateStateTask: true
        );
        $this->ed->dispatch($event);

        $serverTasks = $this->getService(ServerTaskRepository::class)->findAll();
        $this->assertCount(1, $serverTasks);
        $task = $serverTasks[0];
        $this->assertSame($server->getId(), $task->getServer()->getId());
        $this->assertSame(['api_workers_updated' => true], $task->getPayload());
    }

    public function test_on_ip_address_queue_update_create_task(): void
    {
        $server = ServerFactory::createOne();
        $ipAddress = IpAddressFactory::createOne(['server' => $server]);

        $updates = new UpdateIpAddressDto();
        $updates->queue = QueueFactory::createOne();

        $event = new IpAddressUpdatedEvent(
            $ipAddress,
            $ipAddress,
            $updates
        );

        $this->ed->dispatch($event);

        $serverTasks = $this->getService(ServerTaskRepository::class)->findAll();
        $this->assertCount(1, $serverTasks);
        $task = $serverTasks[0];
        $this->assertSame($server->getId(), $task->getServer()->getId());
        $this->assertSame(['api_workers_updated' => false], $task->getPayload());
    }

}