<?php

namespace App\Tests\Api\Sudo\Kyc;

use App\Api\Sudo\Controller\KycController;
use App\Entity\Kyc;
use App\Entity\Send;
use App\Entity\Type\DomainStatus;
use App\Entity\Type\KycStatus;
use App\Service\App\Config;
use App\Service\Instance\InstanceService;
use App\Service\Kyc\KycEmailService;
use App\Service\Kyc\KycService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\KycFactory;
use App\Tests\Factory\QueueFactory;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Billing\CreateSubscription;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Billing\CreateSubscriptionResponse;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use Hyvor\Internal\Component\Component;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KycController::class)]
#[CoversClass(KycService::class)]
#[CoversClass(KycEmailService::class)]
class ApproveKycTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';
    }

    private function seedSystemProjectInfra(): void
    {
        /** @var InstanceService $instanceService */
        $instanceService = $this->container->get(InstanceService::class);
        $instance = $instanceService->tryGetInstance() ?? InstanceFactory::new()->withDefaultDkim()->create();

        /** @var Config $config */
        $config = $this->container->get(Config::class);

        DomainFactory::createOne([
            'project' => $instance->getSystemProject(),
            'domain' => $config->getInstanceDomain(),
            'status' => DomainStatus::ACTIVE,
        ]);

        QueueFactory::createTransactional();
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

    public function test_approving_marks_the_organizations_previous_active_kyc_as_stale(): void
    {
        $previous = KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::REJECTED,
        ]);
        $kyc = KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::PENDING,
        ]);

        $this->getComms()->addResponse(
            CreateSubscription::class,
            function () {
                return new CreateSubscriptionResponse(999);
            }
        );

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(200, $response->getStatusCode());

        $kycEntity = $this->em->getRepository(Kyc::class)->find($kyc->getId());
        $this->assertNotNull($kycEntity);
        $this->assertSame(KycStatus::APPROVED, $kycEntity->getStatus());

        $previousReloaded = $this->em->getRepository(Kyc::class)->find($previous->getId());
        $this->assertNotNull($previousReloaded);
        $this->assertSame(KycStatus::STALE, $previousReloaded->getStatus());
    }

    public function test_sends_approval_email_when_subscription_is_created(): void
    {
        $this->seedSystemProjectInfra();
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $this->getComms()->addResponse(
            CreateSubscription::class,
            function () {
                return new CreateSubscriptionResponse(999);
            }
        );

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(200, $response->getStatusCode());

        $sends = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $sends);
        $this->assertSame('Your KYC has been approved', $sends[0]->getSubject());
    }

    public function test_sends_approval_email_when_subscription_creation_fails(): void
    {
        $this->seedSystemProjectInfra();
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $this->getComms()->addResponse(
            CreateSubscription::class,
            function () {
                throw new CommsApiFailedException('Card declined.');
            }
        );

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(200, $response->getStatusCode());

        $sends = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $sends);
        $this->assertSame('Your KYC has been approved', $sends[0]->getSubject());
    }

    public function test_sends_approval_email_even_when_not_the_organizations_first_decision(): void
    {
        $this->seedSystemProjectInfra();

        KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::STALE,
        ]);
        $kyc = KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::PENDING,
        ]);

        $this->getComms()->addResponse(
            CreateSubscription::class,
            function () {
                return new CreateSubscriptionResponse(999);
            }
        );

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/approve');
        $this->assertSame(200, $response->getStatusCode());

        $sends = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $sends);
        $this->assertSame('Your KYC has been approved', $sends[0]->getSubject());
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
