<?php

namespace App\Tests\Api\Console\Project;

use App\Api\Console\Controller\ProjectController;
use App\Api\Console\Object\ProjectObject;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectObject::class)]
class CreateProjectTest extends WebTestCase
{
    public function test_create_project_valid(): void
    {
        $response = $this->consoleApi(
            null,
            'POST',
            '/project',
            [
                'name' => 'Valid Project Name'
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('created_at', $json);
        $this->assertArrayHasKey('name', $json);
    }
}