<?php

namespace App\Tests\Api\Console\Project;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\ProjectController;
use App\Api\Console\Object\ProjectObject;
use App\Entity\Project;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectObject::class)]
class UpdateProjectTest extends WebTestCase
{
    public function test_update_project(): void
    {
        $project = ProjectFactory::createOne([
            'name' => 'Original Project Name',
        ]);

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/project',
            [
                'name' => 'Updated Project Name',
            ],
            scopes: [Scope::PROJECT_WRITE]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertArrayHasKey('id', $content);
        $this->assertArrayHasKey('name', $content);
        $this->assertArrayHasKey('created_at', $content);
        $this->assertSame($project->getId(), $content['id']);
        $this->assertSame('Updated Project Name', $content['name']);

        $projectDb = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($projectDb);
        $this->assertSame('Updated Project Name', $projectDb->getName());
    }

    public function test_update_project_with_blank_name(): void
    {
        $project = ProjectFactory::createOne([
            'name' => 'Original Project Name',
        ]);

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/project',
            [
                'name' => '',
            ]
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertHasViolation('name', 'This value is too short. It should have 1 character or more.');
    }

    public function test_update_project_with_long_name(): void
    {
        $project = ProjectFactory::createOne([
            'name' => 'Original Project Name',
        ]);

        $longName = str_repeat('a', 256); // 256 characters, exceeds max length of 255

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/project',
            [
                'name' => $longName,
            ]
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertHasViolation('name', 'This value is too long. It should have 255 characters or less.');
    }

    public function test_update_project_without_name(): void
    {
        $project = ProjectFactory::createOne([
            'name' => 'Original Project Name',
        ]);

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/project',
            []
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertSame('Original Project Name', $content['name']);

        // Verify the name wasn't changed in the database
        $projectDb = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($projectDb);
        $this->assertSame('Original Project Name', $projectDb->getName());
    }
} 
