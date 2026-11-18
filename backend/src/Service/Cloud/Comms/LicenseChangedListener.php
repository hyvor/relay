<?php

namespace App\Service\Cloud\Comms;

use App\Service\Cloud\Billing\LicenseService;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\License\LicenseChanged;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class LicenseChangedListener
{
    public function __construct(
        private LicenseService $licenseService,
    ) {}

    public function __invoke(LicenseChanged $event): void
    {
        $this->licenseService->clearLicenseCache($event->getOrganizationId());
    }
}
