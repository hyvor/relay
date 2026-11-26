<?php

namespace App\Service\Cloud;

use App\Service\Send\EmailAddressFormat;
use App\Service\Send\Event\SendValidatingEvent;
use Hyvor\Internal\InternalConfig;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mime\Address;

#[AsEventListener]
class SendValidatingListener
{
    private const string SIMULATOR_DOMAIN = 'simulator.relay.hyvor.com';

    public function __construct(
        private InternalConfig $internalConfig,
        private SendRestrictionService $sendRestrictionService,
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

        if (count($recipients) === 0 || !$this->sendRestrictionService->isRestricted($organizationId)) {
            return;
        }

        foreach ($recipients as $recipient) {
            if (!$this->organizationMemberService->isMember($organizationId, $recipient->getAddress())) {
                $event->error = "Emails can only be sent to @" . self::SIMULATOR_DOMAIN . " or members of your organization until your KYC is approved and your subscription is active";
                return;
            }
        }
    }
}
