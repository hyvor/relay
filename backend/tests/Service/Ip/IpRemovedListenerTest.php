<?php

namespace App\Tests\Service\Ip;

use App\Service\Ip\Event\IpRemovedEvent;
use App\Service\Ip\IpRemovedListener;
use App\Service\Send\Message\RouteNullIpsMessage;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IpRemovedListener::class)]
#[CoversClass(IpRemovedEvent::class)]
class IpRemovedListenerTest extends KernelTestCase
{

    public function test_dispatches_route_null_ips_message(): void
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

    public function test_no_message_when_queue_is_null(): void
    {
        $ipAddress = IpAddressFactory::createOne(['queue' => null]);

        $event = new IpRemovedEvent($ipAddress->_real());
        $this->getEd()->dispatch($event);

        $transport = $this->transport('async');
        $this->assertCount(0, $transport->dispatched());
    }

}
