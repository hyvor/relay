<?php

namespace App\Tests\Api\Sudo\Project;

use App\Api\Sudo\Controller\ProjectController;
use App\Api\Sudo\Object\ProjectObject;
use App\Entity\Project;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\ClockAwareTrait;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectObject::class)]
#[CoversClass(ProjectService::class)]
class SudoProjectTest extends WebTestCase
{
    use ClockAwareTrait;

    public function test_get_returns_soft_deleted_project(): void
    {
        $project = ProjectFactory::createOne([
            'deleted_at' => $this->now()->modify('-1 day'),
        ]);

        $this->sudoApi('GET', '/project/' . $project->getId());

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertSame($project->getId(), $json['id']);
        $this->assertNotNull($json['deleted_at']);
    }

    public function test_get_returns_404_for_unknown_project(): void
    {
        $this->sudoApi('GET', '/project/99999');
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_undelete_clears_deleted_at(): void
    {
        $project = ProjectFactory::createOne([
            'deleted_at' => $this->now()->modify('-1 day'),
        ]);

        $this->sudoApi('POST', '/project/' . $project->getId() . '/undelete');

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertNull($json['deleted_at']);

        $this->em->clear();
        $projectDb = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($projectDb);
        $this->assertNull($projectDb->getDeletedAt());
    }

    public function test_undelete_returns_404_for_unknown_project(): void
    {
        $this->sudoApi('POST', '/project/99999/undelete');
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_get_requires_sudo(): void
    {
        $project = ProjectFactory::createOne();
        $this->sudoApi(
            'GET',
            '/project/' . $project->getId(),
            createSudoUser: false,
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function test_list_soft_deleted_returns_only_deleted_projects(): void
    {
        ProjectFactory::createOne(['deleted_at' => null]);
        $deleted1 = ProjectFactory::createOne(['deleted_at' => $this->now()->modify('-2 days')]);
        $deleted2 = ProjectFactory::createOne(['deleted_at' => $this->now()->modify('-1 day')]);

        $this->sudoApi('GET', '/project/soft-deleted');

        $this->assertResponseStatusCodeSame(200);
        /** @var array<array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(2, $json);
        $ids = array_column($json, 'id');
        $this->assertContains($deleted1->getId(), $ids);
        $this->assertContains($deleted2->getId(), $ids);
    }

    public function test_list_soft_deleted_orders_by_deleted_at_asc(): void
    {
        $newer = ProjectFactory::createOne(['deleted_at' => $this->now()->modify('-1 day')]);
        $older = ProjectFactory::createOne(['deleted_at' => $this->now()->modify('-5 days')]);

        $this->sudoApi('GET', '/project/soft-deleted');

        $this->assertResponseStatusCodeSame(200);
        /** @var array<array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertSame($older->getId(), $json[0]['id']);
        $this->assertSame($newer->getId(), $json[1]['id']);
    }

    public function test_list_soft_deleted_respects_limit_and_offset(): void
    {
        ProjectFactory::createOne(['deleted_at' => $this->now()->modify('-3 days')]);
        $second = ProjectFactory::createOne(['deleted_at' => $this->now()->modify('-2 days')]);
        ProjectFactory::createOne(['deleted_at' => $this->now()->modify('-1 day')]);

        $this->sudoApi('GET', '/project/soft-deleted?limit=1&offset=1');

        $this->assertResponseStatusCodeSame(200);
        /** @var array<array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(1, $json);
        $this->assertSame($second->getId(), $json[0]['id']);
    }

    public function test_list_soft_deleted_requires_sudo(): void
    {
        ProjectFactory::createOne(['deleted_at' => $this->now()->modify('-1 day')]);

        $this->sudoApi('GET', '/project/soft-deleted', createSudoUser: false);

        $this->assertResponseStatusCodeSame(403);
    }
}
