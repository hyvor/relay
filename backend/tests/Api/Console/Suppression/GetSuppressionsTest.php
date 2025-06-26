<?php

namespace App\Tests\Api\Console\Suppression;

use App\Api\Console\Controller\SuppressionController;
use App\Api\Console\Object\SuppressionObject;
use App\Service\Suppression\SuppressionService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SuppressionFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SuppressionController::class)]
#[CoversClass(SuppressionService::class)]
#[CoversClass(SuppressionObject::class)]
class GetSuppressionsTest extends WebTestCase
{
    public function test_get_suppresions(): void
    {
        $project = ProjectFactory::createOne();

        $otherProject = ProjectFactory::createOne();

        $suppressions = SuppressionFactory::createMany(5, [
            'project' => $project,
        ]);

        $otherSuppressions = SuppressionFactory::createMany(5, [
            'project' => $otherProject,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/suppressions'
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertCount(5, $content);

        foreach ($content as $key =>  $delivery) {
            $this->assertIsArray($delivery);
            $this->assertArrayHasKey('id', $delivery);
            $this->assertArrayHasKey('created_at', $delivery);
            $this->assertArrayHasKey('email', $delivery);
            $this->assertArrayHasKey('reason', $delivery);
            $this->assertArrayHasKey('description', $delivery);
            $this->assertSame($suppressions[$key]->getId(), $delivery['id']);
            $this->assertSame($suppressions[$key]->getEmail(), $delivery['email']);
            $this->assertSame($suppressions[$key]->getReason()->value, $delivery['reason']);
            $this->assertSame($suppressions[$key]->getDescription(), $delivery['description']);
        }
    }

    public function test_get_suppresions_with_email_search(): void
    {
        $project = ProjectFactory::createOne();

        $suppression = SuppressionFactory::createOne([
            'project' => $project,
            'email' => 'thibault@hyvor.com'
        ]);

        SuppressionFactory::createOne( [
            'project' => $project,
            'email' => 'supun@hyvor.com'
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/suppressions?email=thibault'
        );

        $this->assertSame(200, $response->getStatusCode());
        $content = $this->getJson();

        $this->assertCount(1, $content);
        $this->assertSame($content[0]['id'], $suppression->getId());
    }
}