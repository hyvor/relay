<?php

namespace App\Tests\Api\Sudo\InfrastructureBounce;

use App\Api\Sudo\Controller\InfrastructureBounceController;
use App\Api\Sudo\Object\InfrastructureBounceObject;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InfrastructureBounceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InfrastructureBounceService::class)]
#[CoversClass(InfrastructureBounceController::class)]
#[CoversClass(InfrastructureBounceObject::class)]
class GetInfrastructureBounceTest extends WebTestCase
{
    public function test_get_all_infrastructure_bounces(): void
    {
        $unreadBounces = InfrastructureBounceFactory::createMany(5, [
            'isRead' => false,
        ]);

        $readBounces = InfrastructureBounceFactory::createMany(5, [
            'isRead' => true,
        ]);

        $response = $this->sudoApi(
            'GET',
            '/infrastructure-bounces',
        );

        $this->assertSame(200, $response->getStatusCode());
        $json = $this->getJson();
        $this->assertCount(10, $json);
    }

    public function test_get_read_infrastructure_bounces(): void
    {
        InfrastructureBounceFactory::createMany(5, [
            'isRead' => false,
        ]);

        $readBounces = InfrastructureBounceFactory::createMany(5, [
            'isRead' => true,
        ]);

        $response = $this->sudoApi(
            'GET',
            '/infrastructure-bounces?is_read=true',
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(5, $json);

        foreach ($json as $bounce) {
            $this->assertTrue($bounce['is_read']);
        }
    }

    public function test_get_unread_infrastructure_bounces(): void
    {
        $unreadBounces = InfrastructureBounceFactory::createMany(5, [
            'isRead' => false,
        ]);

        InfrastructureBounceFactory::createMany(5, [
            'isRead' => true,
        ]);

        $response = $this->sudoApi(
            'GET',
            '/infrastructure-bounces?is_read=false',
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(5, $json);

        foreach ($json as $bounce) {
            $this->assertFalse($bounce['is_read']);
        }
    }

    public function test_pagination(): void
    {
        InfrastructureBounceFactory::createMany(15, [
            'isRead' => false,
        ]);

        $response = $this->sudoApi(
            'GET',
            '/infrastructure-bounces?limit=10&offset=0',
        );

        $this->assertSame(200, $response->getStatusCode());
        $json = $this->getJson();
        $this->assertCount(10, $json);
    }
}
