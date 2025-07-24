<?php

namespace App\Tests\Api\Console\Project;

use App\Api\Console\Controller\ProjectController;
use App\Api\Console\Object\ProjectObject;
use App\Repository\ProjectRepository;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectRepository::class)]
#[CoversClass(ProjectObject::class)]
class GetProjectTest extends WebTestCase
{
    public function test_get_project_valid(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/project'
        );

        $this->assertSame(200, $response->getStatusCode());

        $json = $this->getJson();

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('created_at', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertSame($project->getId(), $json['id']);
        $this->assertSame($project->getName(), $json['name']);
    }
}
