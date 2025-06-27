<?php

namespace App\Tests\Api\Console\Suppression;

use App\Entity\Suppression;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SuppressionFactory;

class DeleteSuppressionTest extends WebTestCase
{
    public function test_delete_suppression(): void
    {
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
        $this->assertNull($suppressionDb, );
    }
}