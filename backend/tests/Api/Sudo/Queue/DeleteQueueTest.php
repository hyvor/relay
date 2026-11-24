<?php

namespace App\Tests\Api\Sudo\Queue;

use App\Api\Sudo\Controller\QueueController;
use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Type\QueueType;
use App\Service\Queue\QueueService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(QueueController::class)]
#[CoversClass(QueueService::class)]
class DeleteQueueTest extends WebTestCase
{

    public function test_delete_queue(): void
    {
        $queue = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);
        $queueId = $queue->getId();
        $project = ProjectFactory::createOne(['queue' => $queue]);

        $this->sudoApi('DELETE', '/queues/' . $queueId);

        $this->assertResponseStatusCodeSame(200);

        $this->em->clear();
        $this->assertNull($this->em->getRepository(Queue::class)->find($queueId));

        $project = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($project);
        $this->assertNull($project->getQueue());
    }

    public function test_404_when_queue_not_found(): void
    {
        $this->sudoApi('DELETE', '/queues/99999');
        $this->assertResponseStatusCodeSame(404);
    }

    #[TestWith([QueueType::TRANSACTIONAL, QueueService::DEFAULT_QUEUE_NAME])]
    #[TestWith([QueueType::DISTRIBUTIONAL, QueueService::TRIAL_QUEUE_NAME])]
    public function test_cannot_delete_reserved_queues(QueueType $type, string $name): void
    {
        $queue = QueueFactory::createOne(['type' => $type, 'name' => $name]);

        $this->sudoApi('DELETE', '/queues/' . $queue->getId());

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Reserved queues cannot be deleted.', $this->getJson()['message']);
    }

    public function test_cannot_delete_queue_with_ip_addresses(): void
    {
        $queue = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);
        IpAddressFactory::createOne(['queue' => $queue]);

        $this->sudoApi('DELETE', '/queues/' . $queue->getId());

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(
            'Unassign all IP addresses from the queue before deleting it.',
            $this->getJson()['message']
        );
    }

    public function test_cannot_delete_queue_with_sends(): void
    {
        $queue = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);
        SendFactory::createOne(['queue' => $queue]);

        $this->sudoApi('DELETE', '/queues/' . $queue->getId());

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(
            'Queues that have been used for sending cannot be deleted.',
            $this->getJson()['message']
        );
    }

}
