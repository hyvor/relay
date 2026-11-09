<?php

namespace App\Tests\Service\Queue;

use App\Service\Queue\QueueService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueueService::class)]
class QueueServiceTest extends KernelTestCase
{

    public function test_get_queues_that_have_no_ip_addresses(): void
    {
        /** @var QueueService $queueService */
        $queueService = $this->container->get(QueueService::class);

        $queue1 = QueueFactory::createOne();
        $this->assertSame([$queue1], $queueService->getQueuesThatHaveNoIpAddresses());

        $ip = IpAddressFactory::createOne([
            'queue' => $queue1
        ]);
        $this->assertSame([], $queueService->getQueuesThatHaveNoIpAddresses());
        $this->assertSame([$queue1], $queueService->getQueuesThatHaveNoIpAddresses([$ip->getId()]));
    }

}