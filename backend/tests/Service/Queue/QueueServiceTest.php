<?php

namespace App\Tests\Service\Queue;

use App\Entity\Type\QueueType;
use App\Service\Queue\QueueService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueueService::class)]
class QueueServiceTest extends KernelTestCase
{

    private QueueService $queueService;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var QueueService $queueService */
        $queueService = $this->container->get(QueueService::class);
        $this->queueService = $queueService;
    }

    public function test_get_queues_that_have_no_ip_addresses(): void
    {
        $queue1 = QueueFactory::createOne();
        $this->assertSame([$queue1], $this->queueService->getQueuesThatHaveNoIpAddresses());

        $ip = IpAddressFactory::createOne([
            'queue' => $queue1
        ]);
        $this->assertSame([], $this->queueService->getQueuesThatHaveNoIpAddresses());
        $this->assertSame([$queue1], $this->queueService->getQueuesThatHaveNoIpAddresses([$ip->getId()]));
    }

    public function test_get_queue_by_type_and_name(): void
    {
        $transactional = QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        $distributional = QueueFactory::createDefault(QueueType::DISTRIBUTIONAL);
        $eu = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);

        $this->assertSame($transactional, $this->queueService->getQueue(QueueType::TRANSACTIONAL, 'default'));
        $this->assertSame($distributional, $this->queueService->getQueue(QueueType::DISTRIBUTIONAL, 'default'));
        $this->assertNull($this->queueService->getQueue(QueueType::TRANSACTIONAL, 'unknown'));

        $this->assertSame([$transactional, $eu], $this->queueService->getQueuesByType(QueueType::TRANSACTIONAL));
    }

    public function test_select_queue_with_one_queue(): void
    {
        $queue = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);
        QueueFactory::createDefault(QueueType::DISTRIBUTIONAL);

        foreach ([1, 2, 3, 100] as $projectId) {
            $this->assertSame($queue, $this->queueService->selectQueue(QueueType::TRANSACTIONAL, $projectId));
        }
    }

    public function test_select_queue_by_project_id(): void
    {
        $queue0 = QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        $queue1 = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'one']);
        $queue2 = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'two']);
        QueueFactory::createOne(['type' => QueueType::DISTRIBUTIONAL, 'name' => 'other']);

        $this->assertSame($queue0, $this->queueService->selectQueue(QueueType::TRANSACTIONAL, 3));
        $this->assertSame($queue1, $this->queueService->selectQueue(QueueType::TRANSACTIONAL, 4));
        $this->assertSame($queue2, $this->queueService->selectQueue(QueueType::TRANSACTIONAL, 5));
        $this->assertSame($queue0, $this->queueService->selectQueue(QueueType::TRANSACTIONAL, 6));
    }

    public function test_select_queue_throws_when_no_queues(): void
    {
        QueueFactory::createDefault(QueueType::DISTRIBUTIONAL);

        $this->expectException(\LogicException::class);
        $this->queueService->selectQueue(QueueType::TRANSACTIONAL, 1);
    }

    public function test_queue_full_name(): void
    {
        $default = QueueFactory::createDefault(QueueType::DISTRIBUTIONAL);
        $custom = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);

        $this->assertSame('distributional:default', $default->getFullName());
        $this->assertSame('transactional:eu', $custom->getFullName());
    }

    public function test_has_sends_ip_addresses_and_project_counts(): void
    {
        $queue = QueueFactory::createOne();
        $other = QueueFactory::createOne();

        $this->assertFalse($this->queueService->hasSends($queue));
        $this->assertFalse($this->queueService->hasIpAddresses($queue));

        SendFactory::createOne(['queue' => $queue]);
        IpAddressFactory::createOne(['queue' => $queue]);
        ProjectFactory::createMany(2, ['queue' => $queue]);
        ProjectFactory::createOne(['queue' => $other]);
        ProjectFactory::createOne();

        $this->assertTrue($this->queueService->hasSends($queue));
        $this->assertTrue($this->queueService->hasIpAddresses($queue));
        $this->assertFalse($this->queueService->hasSends($other));

        $this->assertSame(
            [$queue->getId() => 2, $other->getId() => 1],
            $this->queueService->getProjectCountsByQueue()
        );
    }

}
