<?php

namespace App\Service\Cloud\Comms;

use App\Service\Queue\ProjectQueueResolver;
use Hyvor\Internal\Bundle\Comms\Event\FromCore\License\LicenseChanged;
use Hyvor\Internal\Component\Component;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class LicenseChangedListener
{
    public function __construct(
        private ProjectQueueResolver $projectQueueResolver,
    ) {
    }

    public function __invoke(LicenseChanged $event): void
    {
        if ($event->getComponent() !== Component::RELAY) {
            return;
        }

        $this->projectQueueResolver->reassignOrganization(
            $event->getOrganizationId(),
            $event->getNewLicense()
        );
    }
}
