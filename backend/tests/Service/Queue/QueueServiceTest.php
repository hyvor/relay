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

    public function test_get_a_queue_that_has_no_ip_address(): void
    {
        /** @var QueueService $queueService */
        $queueService = $this->container->get(QueueService::class);

        $queue1 = QueueFactory::createOne();
        $this->assertSame($queue1->getId(), $queueService->getAQueueThatHasNoIpAddresses()?->getId());

        IpAddressFactory::createOne([
            'queue' => $queue1
        ]);
        $this->assertNull($queueService->getAQueueThatHasNoIpAddresses());
    }

}