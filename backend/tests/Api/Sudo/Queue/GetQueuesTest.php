<?php

namespace App\Tests\Api\Sudo\Queue;

use App\Api\Sudo\Controller\QueueController;
use App\Api\Sudo\Object\QueueObject;
use App\Service\Queue\QueueService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
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
        $queue2 = QueueFactory::createDistributional();

        $response = $this->sudoApi("GET", "/queues");

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertCount(2, $json);
    }

    public function test_get_queues_with_ip_count(): void
    {
        $queue1 = QueueFactory::createTransactional();
        $queue2 = QueueFactory::createDistributional();

        IpAddressFactory::createOne(['queue' => $queue1]);
        IpAddressFactory::createOne(['queue' => $queue1]);
        IpAddressFactory::createOne(['queue' => null]);

        $this->sudoApi("GET", "/queues");

        $this->assertResponseStatusCodeSame(200);

        /** @var array<array<string, mixed>> $json */
        $json = $this->getJson();

        $byId = [];
        foreach ($json as $item) {
            $byId[$item['id']] = $item;
        }

        $this->assertEquals(2, $byId[$queue1->getId()]['ip_count']);
        $this->assertEquals(0, $byId[$queue2->getId()]['ip_count']);
    }

}