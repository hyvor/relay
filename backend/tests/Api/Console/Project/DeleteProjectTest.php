<?php

namespace App\Tests\Api\Console\Project;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\ProjectController;
use App\Entity\Project;
use App\Service\Project\Event\ProjectsDeletedEvent;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\ClockAwareTrait;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectsDeletedEvent::class)]
class DeleteProjectTest extends WebTestCase
{
    use ClockAwareTrait;

    public function test_delete_project_sets_deleted_at(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'DELETE',
            '/project',
            scopes: [Scope::PROJECT_WRITE]
        );

        $this->assertSame(204, $response->getStatusCode());

        $this->em->clear();
        $projectDb = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($projectDb);
        $this->assertNotNull($projectDb->getDeletedAt());
    }

    public function test_already_deleted_project_is_rejected_at_auth(): void
    {
        $project = ProjectFactory::createOne([
            'deleted_at' => $this->now()->modify('-1 day'),
        ]);

        $response = $this->consoleApi(
            $project,
            'DELETE',
            '/project',
            scopes: [Scope::PROJECT_WRITE]
        );

        $this->assertSame(403, $response->getStatusCode());

        $this->em->clear();
        $projectDb = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($projectDb);
        $this->assertNotNull($projectDb->getDeletedAt());
    }

    public function test_service_delete_is_idempotent(): void
    {
        $project = ProjectFactory::createOne([
            'deleted_at' => $this->now()->modify('-1 day'),
        ]);
        $beforeTimestamp = $project->getDeletedAt()?->getTimestamp();

        /** @var ProjectService $service */
        $service = $this->container->get(ProjectService::class);
        $service->deleteProject($project->_real());

        $this->em->clear();
        $projectDb = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($projectDb);
        $this->assertSame($beforeTimestamp, $projectDb->getDeletedAt()?->getTimestamp());
    }

    public function test_cannot_delete_system_project(): void
    {
        $instance = InstanceFactory::createOne();
        $systemProject = $instance->getSystemProject();

        $this->expectException(\LogicException::class);

        /** @var ProjectService $service */
        $service = $this->container->get(ProjectService::class);
        $service->deleteProject($systemProject);
    }
}
