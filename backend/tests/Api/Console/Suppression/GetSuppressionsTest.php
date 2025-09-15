<?php

namespace App\Tests\Api\Console\Suppression;

use App\Api\Console\Controller\SuppressionController;
use App\Api\Console\Object\SuppressionObject;
use App\Entity\Type\SuppressionReason;
use App\Repository\SuppressionRepository;
use App\Service\Suppression\SuppressionService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SuppressionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(SuppressionController::class)]
#[CoversClass(SuppressionService::class)]
#[CoversClass(SuppressionObject::class)]
#[CoversClass(SuppressionRepository::class)]
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

        foreach ($content as $key => $delivery) {
            $this->assertIsArray($delivery);
            $this->assertArrayHasKey('id', $delivery);
            $this->assertArrayHasKey('created_at', $delivery);
            $this->assertArrayHasKey('email', $delivery);
            $this->assertArrayHasKey('reason', $delivery);
            $this->assertArrayHasKey('description', $delivery);

            // Ensure $key is treated as integer for array access
            $suppressionIndex = (int)$key;
            $this->assertSame($suppressions[$suppressionIndex]->getId(), $delivery['id']);
            $this->assertSame($suppressions[$suppressionIndex]->getEmail(), $delivery['email']);
            $this->assertSame($suppressions[$suppressionIndex]->getReason()->value, $delivery['reason']);
            $this->assertSame($suppressions[$suppressionIndex]->getDescription(), $delivery['description']);
        }
    }

    public function test_get_suppresions_with_email_search(): void
    {
        $project = ProjectFactory::createOne();

        $suppression = SuppressionFactory::createOne([
            'project' => $project,
            'email' => 'thibault@hyvor.com'
        ]);

        SuppressionFactory::createOne([
            'project' => $project,
            'email' => 'supun@hyvor.com'
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/suppressions?email=thibault'
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<array<string, mixed>> $content */
        $content = $this->getJson();

        $this->assertCount(1, $content);
        $this->assertArrayHasKey(0, $content);
        $this->assertSame($content[0]['id'], $suppression->getId());
    }

    #[TestWith(['bounce'])]
    #[TestWith(['complaint'])]
    public function test_get_suppressions_with_reason_filter(string $reason): void
    {
        $project = ProjectFactory::createOne();

        $bounceSuppression = SuppressionFactory::createOne([
            'project' => $project,
            'reason' => SuppressionReason::BOUNCE,
            'email' => 'bounce@example.com'
        ]);

        $complaintSuppression = SuppressionFactory::createOne([
            'project' => $project,
            'reason' => SuppressionReason::COMPLAINT,
            'email' => 'complaint@example.com'
        ]);

        // Test filtering by bounce
        $response = $this->consoleApi(
            $project,
            'GET',
            '/suppressions?reason=' . $reason
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<array<string, mixed>> $content */
        $content = $this->getJson();

        $this->assertCount(1, $content);
        $this->assertArrayHasKey(0, $content);
        $this->assertSame(
            $content[0]['id'],
            $reason === 'bounce' ? $bounceSuppression->getId() : $complaintSuppression->getId()
        );
        $this->assertSame($content[0]['reason'], $reason);
    }

    public function test_get_suppressions_with_combined_filters(): void
    {
        $project = ProjectFactory::createOne();

        $bounceSuppression = SuppressionFactory::createOne([
            'project' => $project,
            'reason' => SuppressionReason::BOUNCE,
            'email' => 'thibault@hyvor.com'
        ]);

        SuppressionFactory::createOne([
            'project' => $project,
            'reason' => SuppressionReason::COMPLAINT,
            'email' => 'supun@hyvor.com'
        ]);

        SuppressionFactory::createOne([
            'project' => $project,
            'reason' => SuppressionReason::BOUNCE,
            'email' => 'ishini@hyvor.com'
        ]);

        // Test filtering by both email and reason
        $response = $this->consoleApi(
            $project,
            'GET',
            '/suppressions?email=thibault&reason=bounce'
        );

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<array<string, mixed>> $content */
        $content = $this->getJson();

        $this->assertCount(1, $content);
        $this->assertArrayHasKey(0, $content);
        $this->assertSame($content[0]['id'], $bounceSuppression->getId());
        $this->assertSame($content[0]['reason'], 'bounce');
        $this->assertIsString($content[0]['email']);
        $this->assertStringContainsString('thibault', $content[0]['email']);
    }
}
