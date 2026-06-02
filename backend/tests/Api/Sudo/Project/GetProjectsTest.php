<?php

namespace App\Tests\Api\Sudo\Project;

use App\Api\Sudo\Controller\ProjectController;
use App\Api\Sudo\Object\OrganizationObject;
use App\Api\Sudo\Object\ProjectObject;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\Auth\AuthUserOrganization;
use Hyvor\Internal\Auth\Dto\Organization;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectObject::class)]
#[CoversClass(OrganizationObject::class)]
class GetProjectsTest extends WebTestCase
{
    protected function shouldEnableAuthFake(): bool
    {
        return false;
    }

    private function fakeAuth(Organization ...$organizations): void
    {
        AuthFake::enableForSymfony(
            $this->container,
            ['id' => 1],
            new AuthUserOrganization(id: 1, name: 'Fake Organization', role: 'admin'),
            organizationsDatabase: $organizations === [] ? null : $organizations
        );
    }

    public function test_lists_projects(): void
    {
        $this->fakeAuth();
        ProjectFactory::createMany(3);

        $response = $this->sudoApi('GET', '/projects');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array{projects: array<int, array<string, mixed>>, orgs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();

        $this->assertArrayHasKey('projects', $json);
        $this->assertArrayHasKey('orgs', $json);
        $this->assertCount(3, $json['projects']);

        foreach ($json['projects'] as $project) {
            $this->assertArrayHasKey('id', $project);
            $this->assertArrayHasKey('name', $project);
            $this->assertArrayHasKey('created_at', $project);
            $this->assertArrayHasKey('organization_id', $project);
            $this->assertArrayHasKey('send_type', $project);
        }
    }

    public function test_includes_organization_names(): void
    {
        $this->fakeAuth(
            AuthFake::generateOrganization(['id' => 100, 'name' => 'Acme Inc']),
            AuthFake::generateOrganization(['id' => 200, 'name' => 'Globex']),
        );

        ProjectFactory::createOne(['name' => 'Project A', 'organization_id' => 100]);
        ProjectFactory::createOne(['name' => 'Project B', 'organization_id' => 200]);

        $response = $this->sudoApi('GET', '/projects');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array{projects: array<int, array<string, mixed>>, orgs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();

        $orgs = $json['orgs'];
        $this->assertCount(2, $orgs);
        foreach ($orgs as $org) {
            $this->assertArrayHasKey('billing_email', $org);
            $this->assertArrayHasKey('billing_address', $org);
        }

        $names = array_column($orgs, 'name');
        $this->assertContains('Acme Inc', $names);
        $this->assertContains('Globex', $names);
    }

    public function test_search_filters_by_name_case_insensitive(): void
    {
        $this->fakeAuth();
        ProjectFactory::createOne(['name' => 'Marketing Campaigns']);
        ProjectFactory::createOne(['name' => 'Transactional Emails']);
        ProjectFactory::createOne(['name' => 'Internal Tools']);

        $response = $this->sudoApi('GET', '/projects?search=marketing');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array{projects: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(1, $json['projects']);
        $this->assertSame('Marketing Campaigns', $json['projects'][0]['name']);
    }

    public function test_filters_by_organization_id(): void
    {
        $this->fakeAuth();
        ProjectFactory::createOne(['name' => 'Org 100 A', 'organization_id' => 100]);
        ProjectFactory::createOne(['name' => 'Org 100 B', 'organization_id' => 100]);
        ProjectFactory::createOne(['name' => 'Org 200', 'organization_id' => 200]);

        $response = $this->sudoApi('GET', '/projects?organization_id=100');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array{projects: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(2, $json['projects']);

        foreach ($json['projects'] as $project) {
            $this->assertSame(100, $project['organization_id']);
        }
    }

    public function test_pagination_with_before_id(): void
    {
        $this->fakeAuth();
        $projects = ProjectFactory::createMany(7);
        $projects = array_reverse($projects);
        $cursor = $projects[4]->getId();

        $response = $this->sudoApi('GET', "/projects?limit=5&before_id={$cursor}");
        $this->assertSame(200, $response->getStatusCode());
        /** @var array{projects: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(2, $json['projects']);

        foreach ($json['projects'] as $project) {
            $this->assertLessThan($cursor, $project['id']);
        }
    }

    public function test_respects_limit(): void
    {
        $this->fakeAuth();
        ProjectFactory::createMany(10);

        $response = $this->sudoApi('GET', '/projects?limit=4');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array{projects: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(4, $json['projects']);
    }

    public function test_fails_when_not_sudo(): void
    {
        $this->fakeAuth();
        $this->sudoApi('GET', '/projects', createSudoUser: false);
        $this->assertResponseStatusCodeSame(403);
    }
}
