<?php

namespace App\Tests\Api\Sudo\Kyc;

use App\Api\Sudo\Controller\KycController;
use App\Entity\Type\KycStatus;
use App\Service\Kyc\KycService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\KycFactory;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\Auth\AuthUserOrganization;
use Hyvor\Internal\Auth\Dto\Organization;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KycController::class)]
#[CoversClass(KycService::class)]
class ListKycsTest extends WebTestCase
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

        $_ENV['DEPLOYMENT'] = 'cloud';
    }

    public function test_lists_kycs_with_organizations(): void
    {
        $this->fakeAuth(
            AuthFake::generateOrganization(['id' => 100, 'name' => 'Acme Inc']),
            AuthFake::generateOrganization(['id' => 200, 'name' => 'Globex']),
        );

        KycFactory::createOne(['organization_id' => 100, 'name' => 'A']);
        KycFactory::createOne(['organization_id' => 200, 'name' => 'B']);

        $response = $this->sudoApi('GET', '/kyc');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{kycs: array<int, array<string, mixed>>, orgs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(2, $json['kycs']);
        $this->assertCount(2, $json['orgs']);

        $names = array_column($json['orgs'], 'name');
        $this->assertContains('Acme Inc', $names);
        $this->assertContains('Globex', $names);
    }

    public function test_filters_by_status(): void
    {
        $this->fakeAuth();
        KycFactory::createOne(['status' => KycStatus::PENDING]);
        KycFactory::createOne(['status' => KycStatus::APPROVED]);
        KycFactory::createOne(['status' => KycStatus::REJECTED]);

        $response = $this->sudoApi('GET', '/kyc?status=approved');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{kycs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(1, $json['kycs']);
        $this->assertSame('approved', $json['kycs'][0]['status']);
    }

    public function test_excludes_stale_kycs_by_default(): void
    {
        $this->fakeAuth();
        KycFactory::createOne(['status' => KycStatus::STALE]);
        KycFactory::createOne(['status' => KycStatus::PENDING]);

        $response = $this->sudoApi('GET', '/kyc');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{kycs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(1, $json['kycs']);
        $this->assertSame('pending', $json['kycs'][0]['status']);
    }

    public function test_can_filter_to_stale_kycs(): void
    {
        $this->fakeAuth();
        KycFactory::createOne(['status' => KycStatus::STALE]);
        KycFactory::createOne(['status' => KycStatus::PENDING]);

        $response = $this->sudoApi('GET', '/kyc?status=stale');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{kycs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(1, $json['kycs']);
        $this->assertSame('stale', $json['kycs'][0]['status']);
    }

    public function test_filters_by_organization_id_and_includes_stale_versions(): void
    {
        $this->fakeAuth();
        KycFactory::createOne(['organization_id' => 100, 'status' => KycStatus::STALE]);
        KycFactory::createOne(['organization_id' => 100, 'status' => KycStatus::REJECTED]);
        KycFactory::createOne(['organization_id' => 200, 'status' => KycStatus::PENDING]);

        $response = $this->sudoApi('GET', '/kyc?organization_id=100');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{kycs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(2, $json['kycs']);

        $statuses = array_column($json['kycs'], 'status');
        $this->assertContains('stale', $statuses);
        $this->assertContains('rejected', $statuses);
    }

    public function test_filters_by_organization_id_and_status_together(): void
    {
        $this->fakeAuth();
        KycFactory::createOne(['organization_id' => 100, 'status' => KycStatus::STALE]);
        KycFactory::createOne(['organization_id' => 100, 'status' => KycStatus::REJECTED]);
        KycFactory::createOne(['organization_id' => 200, 'status' => KycStatus::REJECTED]);

        $response = $this->sudoApi('GET', '/kyc?organization_id=100&status=rejected');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{kycs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $this->assertCount(1, $json['kycs']);
        $this->assertSame('rejected', $json['kycs'][0]['status']);
    }

    public function test_fails_validation_on_invalid_organization_id(): void
    {
        $this->fakeAuth();
        $this->sudoApi('GET', '/kyc?organization_id=0');
        $this->assertHasViolation('organization_id');
    }

    public function test_sorts_by_status(): void
    {
        $this->fakeAuth();
        KycFactory::createOne(['status' => KycStatus::REJECTED]);
        KycFactory::createOne(['status' => KycStatus::PENDING]);
        KycFactory::createOne(['status' => KycStatus::APPROVED]);

        $response = $this->sudoApi('GET', '/kyc?sort_by=status&sort=asc');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{kycs: array<int, array<string, mixed>>} $json */
        $json = $this->getJson();
        $statuses = array_column($json['kycs'], 'status');
        $this->assertSame(['pending', 'approved', 'rejected'], $statuses);
    }

    public function test_respects_limit(): void
    {
        $this->fakeAuth();
        KycFactory::createMany(5);

        $response = $this->sudoApi('GET', '/kyc?limit=2');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{kycs: array<int, mixed>} $json */
        $json = $this->getJson();
        $this->assertCount(2, $json['kycs']);
    }

    public function test_fails_validation_on_invalid_status(): void
    {
        $this->fakeAuth();
        $this->sudoApi('GET', '/kyc?status=notarealstatus');
        $this->assertHasViolation('status');
    }

    public function test_fails_validation_on_invalid_sort_by(): void
    {
        $this->fakeAuth();
        $this->sudoApi('GET', '/kyc?sort_by=full_name');
        $this->assertHasViolation('sort_by');
    }

    public function test_returns_404_on_non_cloud_deployment(): void
    {
        $this->fakeAuth();
        $_ENV['DEPLOYMENT'] = 'on-prem';

        $response = $this->sudoApi('GET', '/kyc');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_fails_when_not_sudo(): void
    {
        $this->fakeAuth();
        $response = $this->sudoApi('GET', '/kyc', createSudoUser: false);
        $this->assertSame(403, $response->getStatusCode());
    }
}
