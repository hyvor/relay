<?php

namespace App\Tests\Api\Sudo\Kyc;

use App\Api\Sudo\Controller\KycController;
use App\Entity\Kyc;
use App\Entity\Type\KycStatus;
use App\Service\Kyc\KycService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\KycFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KycController::class)]
#[CoversClass(KycService::class)]
class RejectKycTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';
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
