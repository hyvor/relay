<?php

namespace App\Tests\Service\Cloud\Comms;

use App\Entity\Type\KycStatus;
use App\Entity\Type\QueueType;
use App\Service\Cloud\Billing\LicenseService;
use App\Service\Cloud\CloudProjectQueueListener;
use App\Service\Cloud\Comms\LicenseChangedListener;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\KycFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use Hyvor\Internal\Billing\BillingFake;
use Hyvor\Internal\Billing\License\RelayLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\License\LicenseChanged;
use Hyvor\Internal\Component\Component;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LicenseChangedListener::class)]
#[CoversClass(LicenseService::class)]
class LicenseChangedListenerTest extends WebTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DEPLOYMENT'] = 'cloud';
    }

    private function licenseChanged(int $orgId): LicenseChanged
    {
        return new LicenseChanged(
            $orgId,
            Component::RELAY,
            new ResolvedLicense(ResolvedLicenseType::TRIAL, RelayLicense::trial()),
            new ResolvedLicense(ResolvedLicenseType::SUBSCRIPTION, RelayLicense::trial()),
        );
    }

    public function test_moves_projects_of_the_organization_to_production(): void
    {
        $default = QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        $trial = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => CloudProjectQueueListener::TRIAL_QUEUE_NAME]);

        KycFactory::createOne(['organization_id' => 1, 'status' => KycStatus::APPROVED]);
        $this->getService(BillingFake::class)->setLicenses([
            1 => new ResolvedLicense(ResolvedLicenseType::SUBSCRIPTION, RelayLicense::trial()),
        ]);

        $projects = ProjectFactory::createMany(2, ['organization_id' => 1, 'queue' => $trial]);
        $otherOrgProject = ProjectFactory::createOne(['organization_id' => 2, 'queue' => $trial]);

        $this->getEd()->dispatch($this->licenseChanged(1));

        foreach ($projects as $project) {
            $this->assertSame($default->getId(), $project->getQueue()?->getId());
        }
        $this->assertSame($trial->getId(), $otherOrgProject->getQueue()?->getId());
    }

    public function test_clears_license_cache(): void
    {
        $billing = $this->getService(BillingFake::class);
        $licenseService = $this->getService(LicenseService::class);

        $oldLicense = new ResolvedLicense(ResolvedLicenseType::TRIAL, RelayLicense::trial());
        $billing->setLicenses([1 => $oldLicense]);
        $licenseService->getCachedLicense(1);

        $newLicense = new ResolvedLicense(ResolvedLicenseType::SUBSCRIPTION, RelayLicense::trial());
        $billing->setLicenses([1 => $newLicense]);
        $this->assertSame(ResolvedLicenseType::TRIAL, $licenseService->getCachedLicense(1)->type);

        $this->getEd()->dispatch(new LicenseChanged(1, Component::RELAY, $oldLicense, $newLicense));

        $this->assertSame(ResolvedLicenseType::SUBSCRIPTION, $licenseService->getCachedLicense(1)->type);
    }
}
