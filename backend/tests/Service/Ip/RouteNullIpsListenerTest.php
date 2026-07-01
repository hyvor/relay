<?php

namespace App\Tests\Service\Ip;

use App\Service\Ip\Dto\UpdateIpAddressDto;
use App\Service\Ip\Event\IpAddressUpdatedEvent;
use App\Service\Ip\Event\IpRemovedEvent;
use App\Service\Ip\RouteNullIpsListener;
use App\Service\Send\Message\RouteNullIpsMessage;
use App\Service\Send\Message\RouteQueueNullIpsToIpMessage;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RouteNullIpsListener::class)]
class RouteNullIpsListenerTest extends KernelTestCase
{

    public function test_dispatches_route_queue_null_ips_message_when_queue_set(): void
    {
        $queue = QueueFactory::createOne();
        $ipAddress = IpAddressFactory::createOne(['queue' => $queue]);

        $updates = new UpdateIpAddressDto();
        $updates->queue = $queue->_real();

        $event = new IpAddressUpdatedEvent(
            $ipAddress->_real(),
            $ipAddress->_real(),
            $updates
        );

        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $sent = $transport->dispatched();

        $this->assertCount(1, $sent);
        $message = $sent->first()->getMessage();
        $this->assertInstanceOf(RouteQueueNullIpsToIpMessage::class, $message);
        $this->assertSame($queue->getId(), $message->queueId);
        $this->assertSame($ipAddress->getId(), $message->ipAddressId);
    }

    public function test_no_message_when_queue_not_set(): void
    {
        $ipAddress = IpAddressFactory::createOne();

        $updates = new UpdateIpAddressDto();

        $event = new IpAddressUpdatedEvent(
            $ipAddress->_real(),
            $ipAddress->_real(),
            $updates
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
            $ipAddress->_real(),
            $ipAddress->_real(),
            $updates
        );

        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(0, $transport->dispatched());
    }

    public function test_dispatches_route_null_ips_message_on_ip_removed(): void
    {
        $queue = QueueFactory::createOne();
        $ipAddress = IpAddressFactory::createOne(['queue' => $queue]);

        $event = new IpRemovedEvent($ipAddress->_real());
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

        $event = new IpRemovedEvent($ipAddress->_real());
        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(0, $transport->dispatched());
    }

}
