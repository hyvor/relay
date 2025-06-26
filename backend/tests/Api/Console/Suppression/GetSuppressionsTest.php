<?php

namespace App\Tests\Api\Console\Suppression;

use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SuppressionFactory;

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
}