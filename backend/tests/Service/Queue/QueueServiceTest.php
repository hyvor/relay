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
        $trial = QueueFactory::createTrial(QueueType::TRANSACTIONAL);

        $this->assertSame($transactional, $this->queueService->getDefaultQueue(QueueType::TRANSACTIONAL));
        $this->assertSame($distributional, $this->queueService->getDefaultQueue(QueueType::DISTRIBUTIONAL));
        $this->assertSame($trial, $this->queueService->getTrialQueue(QueueType::TRANSACTIONAL));
        $this->assertNull($this->queueService->getTrialQueue(QueueType::DISTRIBUTIONAL));
        $this->assertNull($this->queueService->getQueue(QueueType::TRANSACTIONAL, 'unknown'));
    }

    public function test_select_default_queue_with_one_queue(): void
    {
        $queue = QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        QueueFactory::createTrial(QueueType::TRANSACTIONAL);
        QueueFactory::createDefault(QueueType::DISTRIBUTIONAL);

        foreach ([1, 2, 3, 100] as $projectId) {
            $this->assertSame($queue, $this->queueService->selectDefaultQueue(QueueType::TRANSACTIONAL, $projectId));
        }
    }

    public function test_select_default_queue_by_project_id(): void
    {
        $queue0 = QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        QueueFactory::createTrial(QueueType::TRANSACTIONAL); // never selected
        $queue1 = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'one']);
        $queue2 = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'two']);
        QueueFactory::createOne(['type' => QueueType::DISTRIBUTIONAL, 'name' => 'other']);

        $this->assertSame($queue0, $this->queueService->selectDefaultQueue(QueueType::TRANSACTIONAL, 3));
        $this->assertSame($queue1, $this->queueService->selectDefaultQueue(QueueType::TRANSACTIONAL, 4));
        $this->assertSame($queue2, $this->queueService->selectDefaultQueue(QueueType::TRANSACTIONAL, 5));
        $this->assertSame($queue0, $this->queueService->selectDefaultQueue(QueueType::TRANSACTIONAL, 6));
    }

    public function test_select_default_queue_throws_when_no_queues(): void
    {
        QueueFactory::createTrial(QueueType::TRANSACTIONAL);

        $this->expectException(\LogicException::class);
        $this->queueService->selectDefaultQueue(QueueType::TRANSACTIONAL, 1);
    }

    public function test_create_default_and_trial_queues_only_creates_missing(): void
    {
        QueueFactory::createDefault(QueueType::TRANSACTIONAL);

        $this->assertFalse($this->queueService->hasDefaultQueues());
        $this->queueService->createDefaultQueues();
        $this->assertTrue($this->queueService->hasDefaultQueues());

        $this->assertFalse($this->queueService->hasTrialQueues());
        $this->queueService->createTrialQueues();
        $this->queueService->createTrialQueues();
        $this->assertTrue($this->queueService->hasTrialQueues());

        $this->assertSame(4, $this->queueService->getQueuesCount());
    }

    public function test_queue_full_name_and_reserved(): void
    {
        $default = QueueFactory::createDefault(QueueType::DISTRIBUTIONAL);
        $trial = QueueFactory::createTrial(QueueType::TRANSACTIONAL);
        $custom = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);

        $this->assertSame('distributional:default', $default->getFullName());
        $this->assertSame('transactional:trial', $trial->getFullName());
        $this->assertSame('transactional:eu', $custom->getFullName());

        $this->assertTrue($default->isReserved());
        $this->assertTrue($trial->isReserved());
        $this->assertFalse($custom->isReserved());
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
