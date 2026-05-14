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
class MarkAllInfrastructureBouncesAsReadTest extends WebTestCase
{
    public function test_marks_all_unread_as_read_and_returns_count(): void
    {
        InfrastructureBounceFactory::createMany(3, [
            'is_read' => false,
        ]);
        InfrastructureBounceFactory::createMany(2, [
            'is_read' => true,
        ]);

        $response = $this->sudoApi(
            'POST',
            '/infrastructure-bounces/mark-all-as-read',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['marked_count' => 3], $this->getJson());

        $this->em->clear();
        $unreadCount = $this->em->getRepository(InfrastructureBounce::class)
            ->count(['is_read' => false]);
        $this->assertSame(0, $unreadCount);
    }

    public function test_returns_zero_when_no_unread(): void
    {
        InfrastructureBounceFactory::createMany(2, [
            'is_read' => true,
        ]);

        $response = $this->sudoApi(
            'POST',
            '/infrastructure-bounces/mark-all-as-read',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['marked_count' => 0], $this->getJson());
    }

    public function test_returns_zero_when_no_bounces(): void
    {
        $response = $this->sudoApi(
            'POST',
            '/infrastructure-bounces/mark-all-as-read',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['marked_count' => 0], $this->getJson());
    }
}
