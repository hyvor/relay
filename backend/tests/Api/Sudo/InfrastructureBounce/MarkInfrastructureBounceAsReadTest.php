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

        $response = $this->sudoApi('PATCH', "/infrastructure-bounces/{$bounce->getId()}/mark-as-read");

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('{}', $response->getContent());

        $bounce = refresh($bounce);
        $this->assertTrue($bounce->isRead());
    }

    public function test_mark_already_read_bounce_as_read(): void
    {
        $bounce = InfrastructureBounceFactory::createOne([
            'is_read' => true,
        ]);

        $response = $this->sudoApi('PATCH', "/infrastructure-bounces/{$bounce->getId()}/mark-as-read");

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('{}', $response->getContent());

        $bounce = refresh($bounce);
        $this->assertTrue($bounce->isRead());
    }
}
