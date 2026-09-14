<?php

namespace App\Tests\Api\Console\Kyc;

use App\Api\Console\Controller\Org\KycController;
use App\Entity\Kyc;
use App\Entity\Type\KycStatus;
use App\Service\Kyc\Event\KycSubmittedEvent;
use App\Service\Kyc\KycService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\KycFactory;
use Hyvor\Internal\Auth\Dto\Organization;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\GetOrganizations;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\GetOrganizationsResponse;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
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
            'country' => 'Sri Lanka',
            'address' => '123 Main Street, Colombo',
            'phone' => '+94771234567',
            'website' => 'https://hyvor.com',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';
        // Note: deliberately not faking the GetOrganizations (payment method) Comms
        // response here. Doing so would resolve the CommsInterface service (and,
        // transitively, InternalConfig) this early, which freezes the deployment
        // value for the rest of the test - breaking tests that override
        // $_ENV['DEPLOYMENT'] afterwards (e.g. test_returns_404_on_non_cloud_deployment).
        // Each test that actually reaches KycService::submit()'s payment-method
        // check calls fakeOrganizationHasPaymentMethod() itself instead.
    }

    private function fakeOrganizationHasPaymentMethod(bool $hasPaymentMethod): void
    {
        $this->getComms()->addResponse(
            GetOrganizations::class,
            function (GetOrganizations $event) use ($hasPaymentMethod) {
                $organizations = [];
                foreach ($event->getOrganizationIds() as $id) {
                    $org = new Organization($id, 'Test Org', 1);
                    $org->setHasPaymentMethod($hasPaymentMethod);
                    $organizations[$id] = $org;
                }
                return new GetOrganizationsResponse($organizations);
            }
        );
    }

    public function test_submits_kyc_for_organization(): void
    {
        $this->fakeOrganizationHasPaymentMethod(true);

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
        $this->assertSame('Sri Lanka', $kyc->getCountry());

        $this->getEd()->assertDispatched(KycSubmittedEvent::class);
    }

    public function test_submits_as_individual_without_business_name(): void
    {
        $payload = $this->validPayload();
        $payload['business_type'] = 'individual';
        unset($payload['business_name']);

        $this->fakeOrganizationHasPaymentMethod(true);

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

        $kyc = $this->em->getRepository(Kyc::class)->findOneBy(['organization_id' => 1]);
        $this->assertNotNull($kyc);
        $this->assertNull($kyc->getBusinessName());
    }

    public function test_fails_validation_when_website_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['website']);

        $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $payload,
            useSession: true
        );

        $this->assertHasViolation('website');
    }

    public function test_resubmits_and_overwrites_previous_data(): void
    {
        KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::REJECTED,
            'full_name' => 'Old Name',
        ]);

        $this->fakeOrganizationHasPaymentMethod(true);

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

    public function test_fails_when_no_payment_method(): void
    {
        $this->fakeOrganizationHasPaymentMethod(false);

        $response = $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $this->validPayload(),
            useSession: true
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertResponseFailed(400, 'Please add a payment method');

        $kyc = $this->em->getRepository(Kyc::class)->findOneBy(['organization_id' => 1]);
        $this->assertNull($kyc);
    }

    public function test_fails_when_payment_method_check_fails(): void
    {
        $this->getComms()->addResponse(GetOrganizations::class, function () {
            throw new CommsApiFailedException();
        });

        $response = $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $this->validPayload(),
            useSession: true
        );

        $this->assertSame(400, $response->getStatusCode());
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

    public function test_fails_validation_when_country_is_not_a_known_country(): void
    {
        $payload = $this->validPayload();
        $payload['country'] = 'LK';

        $this->consoleApi(
            null,
            'POST',
            '/kyc',
            $payload,
            useSession: true
        );

        $this->assertHasViolation('country');
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
