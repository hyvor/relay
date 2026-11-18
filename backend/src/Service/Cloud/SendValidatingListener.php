<?php

namespace App\Service\Cloud;

use App\Entity\Type\KycStatus;
use App\Service\Cloud\Billing\LicenseService;
use App\Service\Kyc\KycService;
use App\Service\Send\EmailAddressFormat;
use App\Service\Send\Event\SendValidatingEvent;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;
use Hyvor\Internal\InternalConfig;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mime\Address;

#[AsEventListener]
class SendValidatingListener
{
    private const string SIMULATOR_DOMAIN = 'simulator.relay.hyvor.com';

    public function __construct(
        private InternalConfig $internalConfig,
        private KycService $kycService,
        private LicenseService $licenseService,
        private OrganizationMemberService $organizationMemberService,
    ) {}

    public function __invoke(SendValidatingEvent $event): void
    {
        if (!$this->internalConfig->getDeployment()->isCloud()) {
            return;
        }

        $organizationId = $event->project->getOrganizationId();
        assert($organizationId !== null);

        $recipients = array_filter(
            $event->recipients,
            fn(Address $recipient) => EmailAddressFormat::getDomainFromEmail($recipient->getAddress()) !== self::SIMULATOR_DOMAIN
        );

        if (count($recipients) === 0 || $this->canSendToAnyone($organizationId)) {
            return;
        }

        foreach ($recipients as $recipient) {
            if (!$this->organizationMemberService->isMember($organizationId, $recipient->getAddress())) {
                $event->error = "Emails can only be sent to @" . self::SIMULATOR_DOMAIN . " or members of your organization until your KYC is approved and your subscription is active";
                return;
            }
        }
    }

    private function canSendToAnyone(int $organizationId): bool
    {
        $kyc = $this->kycService->getActiveByOrganizationId($organizationId);
        if ($kyc?->getStatus() !== KycStatus::APPROVED) {
            return false;
        }

        $licenseType = $this->licenseService->getCachedLicense($organizationId)->type;
        return $licenseType === ResolvedLicenseType::SUBSCRIPTION ||
            $licenseType === ResolvedLicenseType::ENTERPRISE_CONTRACT;
    }
}
