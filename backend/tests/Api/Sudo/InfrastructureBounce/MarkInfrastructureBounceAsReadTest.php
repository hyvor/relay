<?php

namespace App\Tests\Api\Sudo\InfrastructureBounce;

use App\Api\Sudo\Controller\InfrastructureBounceController;
use App\Api\Sudo\Object\InfrastructureBounceObject;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InfrastructureBounceFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use function Zenstruck\Foundry\Persistence\refresh;

#[CoversClass(InfrastructureBounceService::class)]
#[CoversClass(InfrastructureBounceController::class)]
#[CoversClass(InfrastructureBounceObject::class)]
class MarkInfrastructureBounceAsReadTest extends WebTestCase
{
    public function test_when_bounce_not_found_returns_404(): void
    {
        $this->sudoApi('PATCH', '/infrastructure-bounces/9999/mark-as-read');
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_mark_unread_bounce_as_read(): void
    {
        $bounce = InfrastructureBounceFactory::createOne([
            'is_read' => false,
        ]);

        $this->sudoApi('PATCH', "/infrastructure-bounces/{$bounce->getId()}/mark-as-read");

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertTrue($json['is_read']);
        $this->assertSame($bounce->getId(), $json['id']);

        $bounce = refresh($bounce);
        $this->assertTrue($bounce->isRead());
    }

    public function test_mark_already_read_bounce_as_read(): void
    {
        $bounce = InfrastructureBounceFactory::createOne([
            'is_read' => true,
        ]);

        $this->sudoApi('PATCH', "/infrastructure-bounces/{$bounce->getId()}/mark-as-read");

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertTrue($json['is_read']);

        $bounce = refresh($bounce);
        $this->assertTrue($bounce->isRead());
    }

    public function test_includes_send_uuid_when_recipient_exists(): void
    {
        $recipient = SendRecipientFactory::createOne();
        $bounce = InfrastructureBounceFactory::createOne([
            'is_read' => false,
            'send_recipient_id' => $recipient->getId(),
        ]);

        $this->sudoApi('PATCH', "/infrastructure-bounces/{$bounce->getId()}/mark-as-read");

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertSame($recipient->getSend()->getUuid(), $json['send_uuid']);
    }
}
