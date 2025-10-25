<?php

namespace App\Tests\Api\Console\Suppression;

use App\Api\Console\Controller\SuppressionController;
use App\Entity\Suppression;
use App\Service\Suppression\Event\SuppressionDeletedEvent;
use App\Service\Suppression\SuppressionService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SuppressionFactory;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SuppressionController::class)]
#[CoversClass(SuppressionService::class)]
#[CoversClass(SuppressionDeletedEvent::class)]
class DeleteSuppressionTest extends WebTestCase
{
    public function test_delete_suppression(): void
    {
        $eventDispatcher = TestEventDispatcher::enable($this->container);
        $project = ProjectFactory::createOne();

        $suppression = SuppressionFactory::createOne([
            'project' => $project
        ]);

        $suppressionId = $suppression->getId();

        $response = $this->consoleApi(
            $project,
            'DELETE',
            '/suppressions/' . $suppressionId
        );

        $this->assertSame(200, $response->getStatusCode());

        $suppressionDb = $this->em->getRepository(Suppression::class)->find($suppressionId);
        $this->assertNull($suppressionDb);
        $eventDispatcher->assertDispatched(SuppressionDeletedEvent::class);
    }
}
