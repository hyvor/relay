<?php

namespace App\Tests\Api\Sudo\Kyc;

use App\Api\Sudo\Controller\KycController;
use App\Entity\Kyc;
use App\Entity\Type\KycStatus;
use App\Service\Kyc\KycService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\KycFactory;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Billing\CreateSubscription;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Billing\CreateSubscriptionResponse;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use Hyvor\Internal\Component\Component;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KycController::class)]
#[CoversClass(KycService::class)]
class ApproveKycTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';
    }

    public function test_approves_and_charges_the_minimum_plan(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $this->getComms()->addResponse(
            CreateSubscription::class,
            function (CreateSubscription $event) {
                return new CreateSubscriptionResponse(999);
            }
        );

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('approved', $json['status']);

        $this->getComms()->assertSent(
            CreateSubscription::class,
            Component::CORE,
            eventValidator: function (CreateSubscription $event) use ($kyc) {
                $this->assertSame($kyc->getOrganizationId(), $event->getOrganizationId());
                $this->assertSame(Component::RELAY, $event->getComponent());
                $this->assertSame('starter', $event->getPlan());
            }
        );

        $kycEntity = $this->em->getRepository(Kyc::class)->find($kyc->getId());
        $this->assertNotNull($kycEntity);
        $this->assertSame(KycStatus::APPROVED, $kycEntity->getStatus());

        $this->assertFalse(
            $this->getTestLogger()->hasErrorThatContains('Failed to create subscription'),
        );
    }

    public function test_approves_even_when_charge_fails(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $this->getComms()->addResponse(
            CreateSubscription::class,
            function () {
                throw new CommsApiFailedException('Card declined.');
            }
        );

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('approved', $json['status']);

        $kycEntity = $this->em->getRepository(Kyc::class)->find($kyc->getId());
        $this->assertNotNull($kycEntity);
        $this->assertSame(KycStatus::APPROVED, $kycEntity->getStatus());

        $this->assertTrue(
            $this->getTestLogger()->hasErrorThatContains(
                'Failed to create subscription for organization ' . $kyc->getOrganizationId(),
            ),
        );
    }

    public function test_approves_even_when_comms_fails(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $this->getComms()->addResponse(
            CreateSubscription::class,
            function () {
                throw new CommsApiFailedException();
            }
        );

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('approved', $json['status']);

        $this->assertTrue(
            $this->getTestLogger()->hasErrorThatContains(
                'Failed to create subscription for organization ' . $kyc->getOrganizationId(),
            ),
        );
    }

    public function test_approves_with_a_private_note(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $this->getComms()->addResponse(
            CreateSubscription::class,
            function () {
                return new CreateSubscriptionResponse(999);
            }
        );

        $response = $this->sudoApi(
            'POST',
            '/kyc/' . $kyc->getId() . '/approve',
            ['note' => 'Verified manually via a phone call.'],
        );
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('Verified manually via a phone call.', $json['note']);

        $kycEntity = $this->em->getRepository(Kyc::class)->find($kyc->getId());
        $this->assertNotNull($kycEntity);
        $this->assertSame('Verified manually via a phone call.', $kycEntity->getNote());
    }

    public function test_fails_when_not_pending(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::APPROVED]);

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_fails_when_not_found(): void
    {
        $response = $this->sudoApi('POST', '/kyc/999999/approve');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_returns_404_on_non_cloud_deployment(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);
        $_ENV['DEPLOYMENT'] = 'on-prem';

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_fails_when_not_sudo(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);
        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve', createSudoUser: false);
        $this->assertSame(403, $response->getStatusCode());
    }
}
