<?php

namespace App\Tests\Api\Sudo\IpAddress;

use App\Api\Sudo\Controller\IpAddressController;
use App\Api\Sudo\Object\IpAddressObject;
use App\Entity\IpAddress;
use App\Entity\Queue;
use App\Service\Ip\IpAddressService;
use App\Service\Queue\QueueService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IpAddressController::class)]
#[CoversClass(IpAddressService::class)]
#[CoversClass(IpAddressObject::class)]
#[CoversClass(QueueService::class)]
class UpdateIpAddressTest extends WebTestCase
{
    public function test_update_ip_address_queue(): void
    {
        $ipAddress = IpAddressFactory::createOne();
        $queue = QueueFactory::createOne();

        $response = $this->sudoApi(
            'PATCH',
            '/ip-addresses/' . $ipAddress->getId(),
            [
                'queue_id' => $queue->getId(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $json = $this->getJson();

        $this->assertEquals($ipAddress->getId(), $json['id']);
        $this->assertEquals($ipAddress->getIpAddress(), $json['ip_address']);

        $ipAddressDb = $this->em->getRepository(IpAddress::class)->findOneBy(['id' => $ipAddress->getId()]);
        $this->assertNotNull($ipAddressDb);
        $this->assertEquals($queue->getName(), $ipAddressDb->getQueue()?->getName());
    }

    public function test_update_ip_address_invalid_queue(): void
    {
        $ipAddress = IpAddressFactory::createOne();

        $this->sudoApi(
            'PATCH',
            '/ip-addresses/' . $ipAddress->getId(),
            [
                'queue_id' => 99999,
            ],
        );

        $this->assertResponseStatusCodeSame(400);

        $response = $this->getJson();
        $this->assertEquals("Queue with ID '99999' does not exist.", $response['message']);
    }

    public function test_update_ip_address_unasign_queue(): void
    {
        $ipAddress = IpAddressFactory::createOne([
            'queue' => $this->em->getRepository(Queue::class)->find(1),
        ]);

        $response = $this->sudoApi(
            'PATCH',
            '/ip-addresses/' . $ipAddress->getId(),
            [
                'queue_id' => null
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $json = $this->getJson();

        $this->assertEquals($ipAddress->getId(), $json['id']);
        $this->assertEquals($ipAddress->getIpAddress(), $json['ip_address']);

        $ipAddressDb = $this->em->getRepository(IpAddress::class)->findOneBy(['id' => $ipAddress->getId()]);
        $this->assertNotNull($ipAddressDb);
        $this->assertNull($ipAddressDb->getQueue());
    }
}
