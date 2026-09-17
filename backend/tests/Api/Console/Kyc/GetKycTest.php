<?php

namespace App\Tests\Api\Console\Kyc;

use App\Api\Console\Controller\Org\KycController;
use App\Entity\Type\KycStatus;
use App\Service\Kyc\KycService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\KycFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KycController::class)]
#[CoversClass(KycService::class)]
class GetKycTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';
    }

    public function test_returns_null_when_not_submitted(): void
    {
        $response = $this->consoleApi(null, 'GET', '/kyc', useSession: true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('null', $response->getContent());
    }

    public function test_returns_existing_kyc_for_organization(): void
    {
        KycFactory::createOne([
            'organization_id' => 1,
            'name' => 'Nadil Karunarathna',
        ]);
        // a kyc for a different organization should not be returned
        KycFactory::createOne(['organization_id' => 2]);

        $response = $this->consoleApi(null, 'GET', '/kyc', useSession: true);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('Nadil Karunarathna', $json['name']);
    }

    public function test_returns_current_kyc_and_ignores_stale_ones(): void
    {
        KycFactory::createOne([
            'organization_id' => 1,
            'name' => 'Old Version',
            'status' => KycStatus::STALE,
        ]);
        KycFactory::createOne([
            'organization_id' => 1,
            'name' => 'Latest Version',
            'status' => KycStatus::PENDING,
        ]);

        $response = $this->consoleApi(null, 'GET', '/kyc', useSession: true);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('Latest Version', $json['name']);
    }

    public function test_returns_404_on_non_cloud_deployment(): void
    {
        $_ENV['DEPLOYMENT'] = 'on-prem';

        $response = $this->consoleApi(null, 'GET', '/kyc', useSession: true);

        $this->assertSame(404, $response->getStatusCode());
    }
}
