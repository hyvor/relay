<?php

namespace App\Service\Kyc;

use App\Entity\Kyc;
use App\Service\App\Config;
use App\Service\Domain\DomainService;
use App\Service\Instance\InstanceService;
use App\Service\Queue\QueueService;
use App\Service\Send\SendService;
use Hyvor\Internal\Component\Component;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class KycEmailService
{
    public function __construct(
        private InstanceService $instanceService,
        private DomainService $domainService,
        private QueueService $queueService,
        private SendService $sendService,
        private Environment $twig,
        private Config $config,
        private LoggerInterface $logger,
    ) {
    }

    public function sendSubmitted(Kyc $kyc, bool $isResubmission = false): void
    {
        $subject = $isResubmission ? 'Your KYC submission has been updated' : 'Your KYC submission has been received';
        
        $bodyMain = $isResubmission 
            ? "We've received your updated KYC submission for Hyvor Relay. Our team will review
                your details and get back. This process may take upto 24 business hours." 
            : "We've received your KYC submission for Hyvor Relay. Our team will review
                your details and get back. This process may take upto 24 business hours.";

        $this->send(
            $kyc,
            $subject,
            [
                'strings' => [
                    'subject' => $subject,
                    'body_main' => $bodyMain,
                ],
                'is_resubmission' => $isResubmission,
            ],
        );
    }

    public function sendApproved(
        Kyc $kyc,
        bool $hasLicense = false,
        bool $subscriptionFailed = false,
    ): void
    {
        assert(!$hasLicense || !$subscriptionFailed, 'Both hasLicense and subscriptionFailed cannot be true at the same time.');

        $subject = 'Your KYC has been approved';

        if ($hasLicense) {
            $bodySecondary = "You're all set and you can start sending emails right away.";
        } elseif ($subscriptionFailed) {
            $bodySecondary = "We couldn't automatically start a subscription for your organization.
                Please start a subscription before you can send emails.";
        } else {
            $bodySecondary = "We've created a subscription to the Starter plan for your organization,
            so you can start sending emails right away.";
        }

        $this->send(
            $kyc,
            $subject,
            [
                'strings' => [
                    'subject' => $subject,
                    'body_main' => 'Good news! Your KYC verification for Hyvor Relay has been approved.',
                    'body_secondary' => $bodySecondary,

                ],
                'has_license' => $hasLicense,
                'subscription_failed' => $subscriptionFailed,
                ...($subscriptionFailed ? ['billing_url' => $this->config->getWebUrl() . '/console/billing'] : []),
            ],
        );
    }

    public function sendRejected(Kyc $kyc): void
    {
        $this->send(
            $kyc,
            'Your KYC submission was rejected',
            [
                'strings' => [
                    'subject' => 'Your KYC submission was rejected',
                    'body_main' => "We're sorry to inform you that your KYC submission for Hyvor Relay has
                        been rejected.",
                    'body_secondary' => "Please review and update your details, then resubmit your KYC for
                        another review.",
                ],
                'reject_reason' => $kyc->getRejectReason(),
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(Kyc $kyc, string $subject, array $context): void
    {
        $instance = $this->instanceService->tryGetInstance();

        if ($instance === null) {
            $this->logger->error('Instance not found; could not send KYC email.', [
                'kycId' => $kyc->getId(),
            ]);
            return;
        }

        $project = $instance->getSystemProject();
        $domain = $this->domainService->getDomainByProjectAndName($project, $this->config->getInstanceDomain());

        if ($domain === null) {
            $this->logger->error('System project domain not found; could not send KYC email.', [
                'kycId' => $kyc->getId(),
            ]);
            return;
        }

        $queue = $this->queueService->getTransactionalQueue();

        if ($queue === null) {
            $this->logger->error('Transactional queue not found; could not send KYC email.', [
                'kycId' => $kyc->getId(),
            ]);
            return;
        }


        try {
            $strings = $context['strings'];
            assert(is_array($strings));
            $strings['name'] = $kyc->getName();
            $context['strings'] = $strings;

            $html = $this->twig->render('mail/kyc.twig', [
                ...$context,
                'component' => Component::RELAY->value,
            ]);

            $this->sendService->createSend(
                $project,
                $domain,
                $queue,
                new Address('noreply@' . $this->config->getInstanceDomain(), Component::RELAY->name()),
                [new Address($kyc->getEmail(), $kyc->getName())],
                [],
                [],
                $subject,
                $html,
                null,
                ['Reply-To' => 'relay.support@hyvor.com'],
                [],
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send KYC email.', [
                'exception' => $e,
                'kycId' => $kyc->getId(),
            ]);
        }
    }
}
