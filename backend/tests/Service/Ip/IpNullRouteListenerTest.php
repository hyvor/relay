<?php

namespace App\Tests\Service\Ip;

use App\Entity\Send;
use App\Service\Ip\Dto\UpdateIpAddressDto;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Ip\Event\IpAddressCreatedEvent;
use App\Service\Ip\Event\IpAddressRemovedEvent;
use App\Service\Ip\Listener\IpNullRouteListener;
use App\Service\Send\Message\RouteNullIpsMessage;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IpNullRouteListener::class)]
class IpNullRouteListenerTest extends KernelTestCase
{

    public function test_on_ip_create_no_null(): void
    {
        $queue = QueueFactory::createOne();
        $ipAddress = IpAddressFactory::createOne(['queue' => $queue]);

        $event = new IpAddressCreatedEvent($ipAddress);
        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(0, $transport->dispatched());
    }

    public function test_on_ip_create_when_queue_null(): void
    {
        $ipAddress = IpAddressFactory::createOne(['queue' => null]);

        $event = new IpAddressCreatedEvent($ipAddress);
        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(0, $transport->dispatched());
    }

    public function test_on_ip_create(): void
    {
        $queue = QueueFactory::createOne();
        $ipAddress = IpAddressFactory::createOne(['queue' => $queue]);

        $send = SendFactory::createOne([
            'queue' => $queue,
            'ipAddress' => null,
            'queued' => true,
        ]);

        $event = new IpAddressCreatedEvent($ipAddress);
        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(1, $transport->dispatched());

        $message = $transport->dispatched()->first()->getMessage();
        $this->assertInstanceOf(RouteNullIpsMessage::class, $message);
        $this->assertSame($queue->getId(), $message->queueId);
    }

    public function test_on_update(): void
    {
        $queue = QueueFactory::createOne();
        $ipAddress = IpAddressFactory::createOne(['queue' => $queue]);

        $send = SendFactory::createOne([
            'queue' => $queue,
            'ipAddress' => null,
            'queued' => true,
        ]);

        $updates = new UpdateIpAddressDto();
        $updates->queue = $queue;

        $event = new IpAddressUpdatedEvent(
            $ipAddress,
            $ipAddress,
            $updates,
        );

        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $sent = $transport->dispatched();

        $this->assertCount(1, $sent);
        $message = $sent->first()->getMessage();
        $this->assertInstanceOf(RouteNullIpsMessage::class, $message);
        $this->assertSame($queue->getId(), $message->queueId);
    }

    public function test_no_message_when_queue_not_set(): void
    {
        $ipAddress = IpAddressFactory::createOne();

        $updates = new UpdateIpAddressDto();

        $event = new IpAddressUpdatedEvent(
            $ipAddress,
            $ipAddress,
            $updates,
        );

        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(0, $transport->dispatched());
    }

    public function test_no_message_when_queue_is_null(): void
    {
        $ipAddress = IpAddressFactory::createOne(['queue' => null]);

        $updates = new UpdateIpAddressDto();
        $updates->queue = null;

        $event = new IpAddressUpdatedEvent(
            $ipAddress,
            $ipAddress,
            $updates,
        );

        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(0, $transport->dispatched());
    }

    public function test_on_remove(): void
    {
        $queue = QueueFactory::createOne();
        $ipAddress = IpAddressFactory::createOne(['queue' => $queue]);

        $send = SendFactory::createOne([
            'queue' => $queue,
            'ipAddress' => null,
            'queued' => true,
        ]);

        $event = new IpAddressRemovedEvent($ipAddress);
        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $sent = $transport->dispatched();

        $this->assertCount(1, $sent);
        $message = $sent->first()->getMessage();
        $this->assertInstanceOf(RouteNullIpsMessage::class, $message);
        $this->assertSame($queue->getId(), $message->queueId);
    }

    public function test_no_message_when_queue_is_null_on_ip_removed(): void
    {
        $ipAddress = IpAddressFactory::createOne(['queue' => null]);

        $event = new IpAddressRemovedEvent($ipAddress);
        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(0, $transport->dispatched());
    }

}
