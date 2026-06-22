<?php

namespace App\Tests\Api\Sudo\Project;

use App\Api\Sudo\Controller\ProjectController;
use App\Api\Sudo\Object\OrganizationObject;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\Auth\AuthUserOrganization;
use Hyvor\Internal\Auth\Dto\Organization;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectController::class)]
#[CoversClass(OrganizationObject::class)]
#[CoversClass(ProjectService::class)]
class GetProjectOrganizationsTest extends WebTestCase
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

    public function test_returns_distinct_organizations_referenced_by_projects(): void
    {
        $this->fakeAuth(
            AuthFake::generateOrganization(['id' => 100, 'name' => 'Acme Inc']),
            AuthFake::generateOrganization(['id' => 200, 'name' => 'Globex']),
        );

        ProjectFactory::createOne(['organization_id' => 100]);
        ProjectFactory::createOne(['organization_id' => 100]);
        ProjectFactory::createOne(['organization_id' => 200]);

        $response = $this->sudoApi('GET', '/projects/organizations');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(2, $json);

        foreach ($json as $org) {
            $this->assertArrayHasKey('billing_email', $org);
            $this->assertArrayHasKey('billing_address', $org);
        }

        $names = array_column($json, 'name');
        $this->assertContains('Acme Inc', $names);
        $this->assertContains('Globex', $names);
    }

    public function test_limits_results_and_orders_by_id_descending(): void
    {
        $this->fakeAuth(
            AuthFake::generateOrganization(['id' => 100, 'name' => 'Acme Inc']),
            AuthFake::generateOrganization(['id' => 200, 'name' => 'Globex']),
            AuthFake::generateOrganization(['id' => 300, 'name' => 'Initech']),
        );

        ProjectFactory::createOne(['organization_id' => 100]);
        ProjectFactory::createOne(['organization_id' => 200]);
        ProjectFactory::createOne(['organization_id' => 300]);

        $response = $this->sudoApi('GET', '/projects/organizations?limit=2');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(2, $json);
        $this->assertSame([300, 200], array_column($json, 'id'));
    }

    public function test_paginates_with_before_id_cursor(): void
    {
        $this->fakeAuth(
            AuthFake::generateOrganization(['id' => 100, 'name' => 'Acme Inc']),
            AuthFake::generateOrganization(['id' => 200, 'name' => 'Globex']),
            AuthFake::generateOrganization(['id' => 300, 'name' => 'Initech']),
        );

        ProjectFactory::createOne(['organization_id' => 100]);
        ProjectFactory::createOne(['organization_id' => 200]);
        ProjectFactory::createOne(['organization_id' => 300]);

        $response = $this->sudoApi('GET', '/projects/organizations?limit=2&before_id=200');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<int, array<string, mixed>> $json */
        $json = $this->getJson();
        $this->assertCount(1, $json);
        $this->assertSame([100], array_column($json, 'id'));
    }

    public function test_returns_empty_when_no_projects(): void
    {
        $this->fakeAuth();

        $response = $this->sudoApi('GET', '/projects/organizations');
        $this->assertSame(200, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertSame([], $json);
    }

    public function test_fails_when_not_sudo(): void
    {
        $this->fakeAuth();
        $this->sudoApi('GET', '/projects/organizations', createSudoUser: false);
        $this->assertResponseStatusCodeSame(403);
    }
}
