<?php

namespace App\Service\Cloud\Comms;

use App\Service\Cloud\Billing\LicenseService;
use App\Service\Queue\ProjectQueueResolver;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\License\LicenseChanged;
use Hyvor\Internal\Component\Component;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class LicenseChangedListener
{
    public function __construct(
        private LicenseService $licenseService,
        private ProjectQueueResolver $projectQueueResolver,
    ) {}

    public function __invoke(LicenseChanged $event): void
    {
        $this->licenseService->clearLicenseCache($event->getOrganizationId());

        if ($event->getComponent() !== Component::RELAY) {
            return;
        }

        $this->projectQueueResolver->reassignOrganization(
            $event->getOrganizationId(),
            $event->getNewLicense()
        );
    }
}
