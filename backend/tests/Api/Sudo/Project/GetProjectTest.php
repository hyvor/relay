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
class GetProjectTest extends WebTestCase
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

    public function test_returns_project_detail(): void
    {
        $this->fakeAuth(
            AuthFake::generateOrganization(['id' => 42, 'name' => 'Acme Inc']),
        );

        $project = ProjectFactory::createOne([
            'name' => 'Marketing Campaigns',
            'organization_id' => 42,
        ]);

        $response = $this->sudoApi('GET', '/projects/' . $project->getId());
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{project: array<string, mixed>, org: array<string, mixed>|null} $json */
        $json = $this->getJson();

        $this->assertArrayHasKey('project', $json);
        $this->assertArrayHasKey('org', $json);

        $jsonProject = $json['project'];
        $this->assertSame($project->getId(), $jsonProject['id']);
        $this->assertSame('Marketing Campaigns', $jsonProject['name']);
        $this->assertSame(42, $jsonProject['organization_id']);
        $this->assertArrayHasKey('user_id', $jsonProject);
        $this->assertArrayHasKey('created_at', $jsonProject);
        $this->assertArrayHasKey('updated_at', $jsonProject);
        $this->assertArrayHasKey('send_type', $jsonProject);

        $this->assertNotNull($json['org']);
        $this->assertSame(42, $json['org']['id']);
        $this->assertSame('Acme Inc', $json['org']['name']);
    }

    public function test_org_is_null_when_organization_not_found(): void
    {
        // Organizations database only knows org 1, but the project references org 999.
        $this->fakeAuth(
            AuthFake::generateOrganization(['id' => 1, 'name' => 'Other Org']),
        );

        $project = ProjectFactory::createOne(['organization_id' => 999]);

        $response = $this->sudoApi('GET', '/projects/' . $project->getId());
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{project: array<string, mixed>, org: array<string, mixed>|null} $json */
        $json = $this->getJson();
        $this->assertNull($json['org']);
    }

    public function test_returns_404_when_unknown(): void
    {
        $this->fakeAuth();
        $response = $this->sudoApi('GET', '/projects/999999');
        $this->assertSame(404, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertSame('Project 999999 not found', $json['message']);
    }

    public function test_fails_when_not_sudo(): void
    {
        $this->fakeAuth();
        $project = ProjectFactory::createOne();

        $this->sudoApi('GET', '/projects/' . $project->getId(), createSudoUser: false);
        $this->assertResponseStatusCodeSame(403);
    }
}
