<?php

namespace App\Tests\Service\Cloud\Comms;

use App\Service\Cloud\Billing\LicenseService;
use App\Service\Cloud\Comms\LicenseChangedListener;
use App\Tests\Case\WebTestCase;
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
