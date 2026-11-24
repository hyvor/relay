<?php

namespace App\Tests\Api\Sudo\Queue;

use App\Api\Sudo\Controller\QueueController;
use App\Api\Sudo\Input\Queue\CreateQueueInput;
use App\Api\Sudo\Object\QueueObject;
use App\Entity\Queue;
use App\Entity\Type\QueueType;
use App\Service\Queue\QueueService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueueController::class)]
#[CoversClass(QueueService::class)]
#[CoversClass(QueueObject::class)]
#[CoversClass(CreateQueueInput::class)]
class CreateQueueTest extends WebTestCase
{

    public function test_create_queue(): void
    {
        $this->sudoApi('POST', '/queues', [
            'type' => 'transactional',
            'name' => 'eu-1',
        ]);

        $this->assertResponseStatusCodeSame(200);

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('transactional', $json['type']);
        $this->assertSame('eu-1', $json['name']);
        $this->assertSame('transactional:eu-1', $json['full_name']);
        $this->assertFalse($json['is_reserved']);
        $this->assertSame(0, $json['projects_count']);

        $queue = $this->em->getRepository(Queue::class)->find($json['id']);
        $this->assertNotNull($queue);
        $this->assertSame(QueueType::TRANSACTIONAL, $queue->getType());
        $this->assertSame('eu-1', $queue->getName());
        $this->assertNull($queue->getDedicatedOrganizationId());
    }

    public function test_same_name_is_allowed_for_different_types(): void
    {
        QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);

        $this->sudoApi('POST', '/queues', [
            'type' => 'distributional',
            'name' => 'eu',
        ]);

        $this->assertResponseStatusCodeSame(200);
    }

    public function test_fails_when_queue_exists(): void
    {
        QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);

        $this->sudoApi('POST', '/queues', [
            'type' => 'transactional',
            'name' => 'eu',
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Queue transactional:eu already exists.', $this->getJson()['message']);
    }

    public function test_fails_for_dedicated_queues(): void
    {
        $this->sudoApi('POST', '/queues', [
            'type' => 'dedicated',
            'name' => 'acme',
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Dedicated queues are not supported yet.', $this->getJson()['message']);
    }

    public function test_validates_name(): void
    {
        $this->sudoApi('POST', '/queues', [
            'type' => 'transactional',
            'name' => 'Invalid:Name',
        ]);

        $this->assertHasViolation(
            'name',
            'Name can only contain lowercase letters, numbers, hyphens and underscores (max 64 characters).'
        );
    }

}
