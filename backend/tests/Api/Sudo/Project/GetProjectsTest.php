<?php

namespace App\Tests\Api\Sudo\Project;

use App\Api\Sudo\Controller\ProjectController;
use App\Api\Sudo\Object\ProjectObject;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectObject::class)]
class GetProjectsTest extends WebTestCase
{
    public function test_lists_projects(): void
    {
        ProjectFactory::createMany(3);

        $response = $this->sudoApi('GET', '/projects');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(3, $json);

        foreach ($json as $project) {
            $this->assertArrayHasKey('id', $project);
            $this->assertArrayHasKey('name', $project);
            $this->assertArrayHasKey('created_at', $project);
            $this->assertArrayHasKey('organization_id', $project);
            $this->assertArrayHasKey('send_type', $project);
        }
    }

    public function test_search_filters_by_name_case_insensitive(): void
    {
        ProjectFactory::createOne(['name' => 'Marketing Campaigns']);
        ProjectFactory::createOne(['name' => 'Transactional Emails']);
        ProjectFactory::createOne(['name' => 'Internal Tools']);

        $response = $this->sudoApi('GET', '/projects?search=marketing');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(1, $json);
        $this->assertSame('Marketing Campaigns', $json[0]['name']);
    }

    public function test_pagination_with_before_id(): void
    {
        $projects = ProjectFactory::createMany(7);
        $projects = array_reverse($projects);
        $cursor = $projects[4]->getId();

        $response = $this->sudoApi('GET', "/projects?limit=5&before_id={$cursor}");
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(2, $json);

        foreach ($json as $project) {
            $this->assertLessThan($cursor, $project['id']);
        }
    }

    public function test_respects_limit(): void
    {
        ProjectFactory::createMany(10);

        $response = $this->sudoApi('GET', '/projects?limit=4');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(4, $json);
    }

    public function test_fails_when_not_sudo(): void
    {
        $this->sudoApi('GET', '/projects', createSudoUser: false);
        $this->assertResponseStatusCodeSame(403);
    }
}
