<?php

namespace App\Tests\Api\Console\Kyc;

use App\Api\Console\Controller\Org\KycController;
use App\Entity\Kyc;
use App\Entity\Type\KycStatus;
use App\Service\Kyc\Event\KycSubmittedEvent;
use App\Service\Kyc\KycService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\KycFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KycController::class)]
#[CoversClass(KycService::class)]
class SubmitKycTest extends WebTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'full_name' => 'Nadil Karunarathna',
            'business_type' => 'company',
            'business_name' => 'HYVOR',
            'country' => 'LK',
            'address' => '123 Main Street, Colombo',
            'phone' => '+94771234567',
            'website' => 'https://hyvor.com',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';
    }

    public function test_submits_kyc_for_organization(): void
    {
        $response = $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $this->validPayload(),
            useSession: true
        );

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('Nadil Karunarathna', $json['full_name']);
        $this->assertSame('company', $json['business_type']);
        $this->assertSame('pending', $json['status']);

        $kyc = $this->em->getRepository(Kyc::class)->findOneBy(['organization_id' => 1]);
        $this->assertNotNull($kyc);
        $this->assertSame('HYVOR', $kyc->getBusinessName());
        $this->assertSame('LK', $kyc->getCountry());

        $this->getEd()->assertDispatched(KycSubmittedEvent::class);
    }

    public function test_submits_as_individual_without_business_name_or_website(): void
    {
        $payload = $this->validPayload();
        $payload['business_type'] = 'individual';
        unset($payload['business_name'], $payload['website']);

        $response = $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $payload,
            useSession: true
        );

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('individual', $json['business_type']);
        $this->assertNull($json['business_name']);
        $this->assertNull($json['website']);

        $kyc = $this->em->getRepository(Kyc::class)->findOneBy(['organization_id' => 1]);
        $this->assertNotNull($kyc);
        $this->assertNull($kyc->getBusinessName());
        $this->assertNull($kyc->getWebsite());
    }

    public function test_resubmits_and_overwrites_previous_data(): void
    {
        KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::REJECTED,
            'full_name' => 'Old Name',
        ]);

        $response = $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $this->validPayload(),
            useSession: true
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('Nadil Karunarathna', $json['full_name']);
        $this->assertSame('pending', $json['status']);

        $count = count($this->em->getRepository(Kyc::class)->findBy(['organization_id' => 1]));
        $this->assertSame(1, $count);
    }

    public function test_fails_when_already_approved(): void
    {
        KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::APPROVED,
        ]);

        $response = $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $this->validPayload(),
            useSession: true
        );

        $this->assertResponseFailed(400);
    }

    public function test_fails_validation_when_full_name_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['full_name']);

        $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $payload,
            useSession: true
        );

        $this->assertHasViolation('full_name');
    }

    public function test_fails_validation_when_business_name_missing_for_company(): void
    {
        $payload = $this->validPayload();
        $payload['business_name'] = '';

        $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $payload,
            useSession: true
        );

        $this->assertHasViolation('business_name');
    }

    public function test_returns_404_on_non_cloud_deployment(): void
    {
        $_ENV['DEPLOYMENT'] = 'on-prem';

        $response = $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $this->validPayload(),
            useSession: true
        );

        $this->assertSame(404, $response->getStatusCode());
    }
}
