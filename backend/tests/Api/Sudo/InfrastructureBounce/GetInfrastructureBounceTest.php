<?php

namespace App\Tests\Api\Sudo\InfrastructureBounce;

use App\Api\Sudo\Controller\InfrastructureBounceController;
use App\Api\Sudo\Object\InfrastructureBounceObject;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InfrastructureBounceFactory;
use App\Tests\Factory\SendRecipientFactory;
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

    public function test_includes_send_uuid_when_recipient_exists(): void
    {
        $recipient = SendRecipientFactory::createOne();
        InfrastructureBounceFactory::createOne([
            'send_recipient_id' => $recipient->getId(),
        ]);
        InfrastructureBounceFactory::createOne([
            'send_recipient_id' => 999999,
        ]);

        $this->sudoApi('GET', '/infrastructure-bounces');

        $this->assertResponseStatusCodeSame(200);
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(2, $json);

        $byRecipientId = [];
        foreach ($json as $bounce) {
            $this->assertIsInt($bounce['send_recipient_id']);
            $byRecipientId[$bounce['send_recipient_id']] = $bounce;
        }

        $this->assertSame(
            $recipient->getSend()->getUuid(),
            $byRecipientId[$recipient->getId()]['send_uuid']
        );
        $this->assertNull($byRecipientId[999999]['send_uuid']);
    }
}
