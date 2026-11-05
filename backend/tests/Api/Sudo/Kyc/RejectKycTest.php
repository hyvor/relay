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
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KycController::class)]
#[CoversClass(KycService::class)]
#[CoversClass(KycEmailService::class)]
class RejectKycTest extends WebTestCase
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

    public function test_rejects_a_pending_kyc(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/reject');
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('rejected', $json['status']);

        $kycEntity = $this->em->getRepository(Kyc::class)->find($kyc->getId());
        $this->assertNotNull($kycEntity);
        $this->assertSame(KycStatus::REJECTED, $kycEntity->getStatus());
    }

    public function test_rejects_with_a_private_note_and_reject_reason(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $response = $this->sudoApi(
            'POST',
            '/kyc/' . $kyc->getId() . '/reject',
            [
                'note' => 'Website looks fake.',
                'reject_reason' => 'We could not verify your business website.',
            ],
        );
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        $this->assertSame('Website looks fake.', $json['note']);
        $this->assertSame('We could not verify your business website.', $json['reject_reason']);

        $kycEntity = $this->em->getRepository(Kyc::class)->find($kyc->getId());
        $this->assertNotNull($kycEntity);
        $this->assertSame('Website looks fake.', $kycEntity->getNote());
        $this->assertSame('We could not verify your business website.', $kycEntity->getRejectReason());
    }

    public function test_rejecting_marks_the_organizations_previous_active_kyc_as_stale(): void
    {
        $previous = KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::APPROVED,
        ]);
        $kyc = KycFactory::createOne([
            'organization_id' => 1,
            'status' => KycStatus::PENDING,
        ]);

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/reject');
        $this->assertSame(200, $response->getStatusCode());

        $kycEntity = $this->em->getRepository(Kyc::class)->find($kyc->getId());
        $this->assertNotNull($kycEntity);
        $this->assertSame(KycStatus::REJECTED, $kycEntity->getStatus());

        $previousReloaded = $this->em->getRepository(Kyc::class)->find($previous->getId());
        $this->assertNotNull($previousReloaded);
        $this->assertSame(KycStatus::STALE, $previousReloaded->getStatus());
    }

    public function test_sends_rejection_email(): void
    {
        $this->seedSystemProjectInfra();
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);

        $response = $this->sudoApi(
            'POST',
            '/kyc/' . $kyc->getId() . '/reject',
            ['reject_reason' => 'We could not verify your business website.'],
        );
        $this->assertSame(200, $response->getStatusCode());

        $sends = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $sends);
        $this->assertSame('Your KYC submission was rejected', $sends[0]->getSubject());
    }

    public function test_sends_rejection_email_even_when_not_the_organizations_first_decision(): void
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

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/reject');
        $this->assertSame(200, $response->getStatusCode());

        $sends = $this->em->getRepository(Send::class)->findAll();
        $this->assertCount(1, $sends);
        $this->assertSame('Your KYC submission was rejected', $sends[0]->getSubject());
    }

    public function test_fails_when_not_pending(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::APPROVED]);

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/reject');
        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_fails_when_not_found(): void
    {
        $response = $this->sudoApi('POST', '/kyc/999999/reject');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_returns_404_on_non_cloud_deployment(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);
        $_ENV['DEPLOYMENT'] = 'on-prem';

        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/reject');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_fails_when_not_sudo(): void
    {
        $kyc = KycFactory::createOne(['status' => KycStatus::PENDING]);
        $response = $this->sudoApi('POST', '/kyc/' . $kyc->getId() . '/reject', createSudoUser: false);
        $this->assertSame(403, $response->getStatusCode());
    }
}
