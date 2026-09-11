<?php

namespace App\Tests\Api\Sudo\InfrastructureBounce;

use App\Api\Sudo\Controller\InfrastructureBounceController;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InfrastructureBounceFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use function Zenstruck\Foundry\Persistence\refresh;

#[CoversClass(InfrastructureBounceService::class)]
#[CoversClass(InfrastructureBounceController::class)]
class MarkAllInfrastructureBouncesAsReadTest extends WebTestCase
{
    public function test_mark_all_unread_as_read(): void
    {
        $unreadBounces = InfrastructureBounceFactory::createMany(3, [
            'is_read' => false,
        ]);
        $readBounces = InfrastructureBounceFactory::createMany(2, [
            'is_read' => true,
        ]);

        $this->sudoApi('POST', '/infrastructure-bounces/mark-all-as-read');

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertSame(3, $json['marked_count']);

        foreach ($unreadBounces as $bounce) {
            $this->assertTrue(refresh($bounce)->isRead());
        }

        foreach ($readBounces as $bounce) {
            $this->assertTrue(refresh($bounce)->isRead());
        }
    }

    public function test_mark_all_when_none_unread(): void
    {
        InfrastructureBounceFactory::createMany(2, [
            'is_read' => true,
        ]);

        $this->sudoApi('POST', '/infrastructure-bounces/mark-all-as-read');

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertSame(0, $json['marked_count']);
    }
}
