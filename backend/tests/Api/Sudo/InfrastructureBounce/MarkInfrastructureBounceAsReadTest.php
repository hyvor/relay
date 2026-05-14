<?php

namespace App\Tests\Api\Sudo\InfrastructureBounce;

use App\Api\Sudo\Controller\InfrastructureBounceController;
use App\Api\Sudo\Object\InfrastructureBounceObject;
use App\Entity\InfrastructureBounce;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InfrastructureBounceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InfrastructureBounceService::class)]
#[CoversClass(InfrastructureBounceController::class)]
#[CoversClass(InfrastructureBounceObject::class)]
class MarkInfrastructureBounceAsReadTest extends WebTestCase
{
    public function test_marks_unread_bounce_as_read(): void
    {
        $bounce = InfrastructureBounceFactory::createOne([
            'is_read' => false,
        ]);
        $id = $bounce->getId();

        $response = $this->sudoApi(
            'PATCH',
            "/infrastructure-bounces/{$id}/mark-as-read",
        );

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame($id, $json['id']);
        $this->assertTrue($json['is_read']);

        $this->em->clear();
        $reloaded = $this->em->getRepository(InfrastructureBounce::class)->find($id);
        $this->assertNotNull($reloaded);
        $this->assertTrue($reloaded->isRead());
    }

    public function test_marks_already_read_bounce_stays_read(): void
    {
        $bounce = InfrastructureBounceFactory::createOne([
            'is_read' => true,
        ]);
        $id = $bounce->getId();

        $response = $this->sudoApi(
            'PATCH',
            "/infrastructure-bounces/{$id}/mark-as-read",
        );

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame($id, $json['id']);
        $this->assertTrue($json['is_read']);
    }

    public function test_returns_404_when_bounce_not_found(): void
    {
        $response = $this->sudoApi(
            'PATCH',
            '/infrastructure-bounces/999999/mark-as-read',
        );

        $this->assertSame(404, $response->getStatusCode());
    }
}
