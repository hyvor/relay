<?php

namespace App\Tests\Service\Cloud;

use App\Entity\Queue;
use App\Entity\Type\KycStatus;
use App\Entity\Type\ProjectSendType;
use App\Entity\Type\QueueType;
use App\Service\Cloud\CloudProjectQueueListener;
use App\Service\Queue\ProjectQueueResolver;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\KycFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use Hyvor\Internal\Billing\BillingFake;
use Hyvor\Internal\Billing\License\RelayLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CloudProjectQueueListener::class)]
class CloudProjectQueueListenerTest extends KernelTestCase
{

    private const int ORG_ID = 1;

    private Queue $transactionalDefault;
    private Queue $transactionalTrial;
    private Queue $distributionalTrial;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';

        $this->transactionalDefault = QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        $this->transactionalTrial = QueueFactory::createTrial(QueueType::TRANSACTIONAL);
        QueueFactory::createDefault(QueueType::DISTRIBUTIONAL);
        $this->distributionalTrial = QueueFactory::createTrial(QueueType::DISTRIBUTIONAL);
    }

    private function setLicense(ResolvedLicenseType $type): void
    {
        /** @var BillingFake $billing */
        $billing = $this->container->get(BillingFake::class);
        $billing->setLicenses([
            self::ORG_ID => new ResolvedLicense($type, RelayLicense::trial()),
        ]);
    }

    private function resolve(ProjectSendType $sendType = ProjectSendType::TRANSACTIONAL, ?int $orgId = self::ORG_ID): Queue
    {
        $project = ProjectFactory::createOne(['organization_id' => $orgId, 'send_type' => $sendType]);

        /** @var ProjectQueueResolver $resolver */
        $resolver = $this->container->get(ProjectQueueResolver::class);
        return $resolver->resolve($project);
    }

    /**
     * @return array<string, array{ResolvedLicenseType, ?KycStatus, bool}>
     */
    public static function eligibilityProvider(): array
    {
        return [
            'trial license, no kyc' => [ResolvedLicenseType::TRIAL, null, false],
            'trial license, approved kyc' => [ResolvedLicenseType::TRIAL, KycStatus::APPROVED, false],
            'expired license, approved kyc' => [ResolvedLicenseType::EXPIRED, KycStatus::APPROVED, false],
            'none license, approved kyc' => [ResolvedLicenseType::NONE, KycStatus::APPROVED, false],
            'subscription, no kyc' => [ResolvedLicenseType::SUBSCRIPTION, null, false],
            'subscription, pending kyc' => [ResolvedLicenseType::SUBSCRIPTION, KycStatus::PENDING, false],
            'subscription, rejected kyc' => [ResolvedLicenseType::SUBSCRIPTION, KycStatus::REJECTED, false],
            'subscription, approved kyc' => [ResolvedLicenseType::SUBSCRIPTION, KycStatus::APPROVED, true],
            'enterprise, approved kyc' => [ResolvedLicenseType::ENTERPRISE_CONTRACT, KycStatus::APPROVED, true],
        ];
    }

    #[DataProvider('eligibilityProvider')]
    public function test_eligibility(ResolvedLicenseType $licenseType, ?KycStatus $kycStatus, bool $production): void
    {
        $this->setLicense($licenseType);

        if ($kycStatus !== null) {
            KycFactory::createOne(['organization_id' => self::ORG_ID, 'status' => $kycStatus]);
        }

        $this->assertSame(
            $production ? $this->transactionalDefault : $this->transactionalTrial,
            $this->resolve()
        );
    }

    public function test_distributional_trial_queue(): void
    {
        $this->setLicense(ResolvedLicenseType::TRIAL);
        $this->assertSame($this->distributionalTrial, $this->resolve(ProjectSendType::DISTRIBUTIONAL));
    }

    public function test_uses_license_from_event(): void
    {
        // billing fake would return a trial license
        $this->setLicense(ResolvedLicenseType::TRIAL);
        KycFactory::createOne(['organization_id' => self::ORG_ID, 'status' => KycStatus::APPROVED]);

        $project = ProjectFactory::createOne(['organization_id' => self::ORG_ID]);

        /** @var ProjectQueueResolver $resolver */
        $resolver = $this->container->get(ProjectQueueResolver::class);
        $queue = $resolver->assign(
            $project,
            new ResolvedLicense(ResolvedLicenseType::SUBSCRIPTION, RelayLicense::trial())
        );

        $this->assertSame($this->transactionalDefault, $queue);
    }

    public function test_trial_queue_and_not_cached_when_license_fetch_fails(): void
    {
        /** @var BillingFake $billing */
        $billing = $this->container->get(BillingFake::class);
        $billing->setLicenses(function () {
            throw new CommsApiFailedException('Core is down');
        });

        $project = ProjectFactory::createOne(['organization_id' => self::ORG_ID]);

        /** @var ProjectQueueResolver $resolver */
        $resolver = $this->container->get(ProjectQueueResolver::class);

        $this->assertSame($this->transactionalTrial, $resolver->resolve($project));
        $this->assertNull($project->getQueue());
        $this->assertTrue($this->getTestLogger()->hasErrorThatContains('Failed to fetch the license'));
    }

    public function test_system_project_uses_default_selection(): void
    {
        $this->setLicense(ResolvedLicenseType::TRIAL);
        $this->assertSame($this->transactionalDefault, $this->resolve(orgId: 0));
    }

    public function test_does_nothing_on_prem(): void
    {
        $_ENV['DEPLOYMENT'] = 'on-prem';
        $this->setLicense(ResolvedLicenseType::TRIAL);
        $this->assertSame($this->transactionalDefault, $this->resolve());
    }

    public function test_throws_when_trial_queue_is_missing(): void
    {
        $this->setLicense(ResolvedLicenseType::TRIAL);
        $this->em->remove($this->transactionalTrial);
        $this->em->flush();

        $this->expectException(\LogicException::class);
        $this->resolve();
    }

}
