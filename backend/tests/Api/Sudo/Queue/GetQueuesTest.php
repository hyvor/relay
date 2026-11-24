<?php

namespace App\Tests\Api\Sudo\Queue;

use App\Api\Sudo\Controller\QueueController;
use App\Api\Sudo\Object\QueueObject;
use App\Entity\Type\QueueType;
use App\Service\Queue\QueueService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueueController::class)]
#[CoversClass(QueueService::class)]
#[CoversClass(QueueObject::class)]
class GetQueuesTest extends WebTestCase
{

    public function test_get_queues(): void
    {
        $queue1 = QueueFactory::createTransactional();
        QueueFactory::createDistributional();
        QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'eu']);

        ProjectFactory::createMany(2, ['queue' => $queue1]);

        $this->sudoApi("GET", "/queues");

        $this->assertResponseStatusCodeSame(200);

        /** @var array<array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(3, $json);

        $this->assertSame(
            ['transactional:default', 'transactional:eu', 'distributional:default'],
            array_column($json, 'full_name')
        );

        $transactional = $json[0];
        $this->assertSame($queue1->getId(), $transactional['id']);
        $this->assertSame('transactional', $transactional['type']);
        $this->assertSame('default', $transactional['name']);
        $this->assertNull($transactional['dedicated_organization_id']);
        $this->assertTrue($transactional['is_reserved']);
        $this->assertSame(2, $transactional['projects_count']);

        $this->assertFalse($json[1]['is_reserved']);
        $this->assertSame(0, $json[1]['projects_count']);
    }
}
