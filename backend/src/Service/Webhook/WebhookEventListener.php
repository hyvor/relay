<?php

namespace App\Service\Webhook;

use App\Api\Console\Object\DomainObject;
use App\Api\Console\Object\SendAttemptObject;
use App\Api\Console\Object\SendObject;
use App\Api\Console\Object\SendRecipientObject;
use App\Api\Console\Object\SuppressionObject;
use App\Entity\Project;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Service\Domain\Event\DomainStatusChangedEvent;
use App\Service\IncomingMail\Event\IncomingBounceEvent;
use App\Service\IncomingMail\Event\IncomingComplaintEvent;
use App\Service\Send\Event\SendRecipientSuppressedEvent;
use App\Service\SendAttempt\Event\SendAttemptCreatedEvent;
use App\Service\SendRecipient\SendRecipientService;
use App\Service\Suppression\Event\SuppressionCreatedEvent;
use App\Service\Suppression\Event\SuppressionDeletedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class WebhookEventListener
{

    public function __construct(
        private WebhookService $webhookService,
        private SendRecipientService $sendRecipientService
    ) {
    }

    /**
     * @param callable(): (object) $objectFactory
     */
    private function createWebhookDelivery(
        Project $project,
        WebhooksEventEnum $eventType,
        callable $objectFactory
    ): void {
        $webhooks = $this->webhookService->getWebhooksForEvent($project, $eventType);

        if (count($webhooks) === 0) {
            return;
        }

        $object = $objectFactory();

        foreach ($webhooks as $webhook) {
            $this->webhookService->createWebhookDelivery(
                $webhook,
                $eventType,
                $object
            );
        }
    }

    #[AsEventListener]
    public function onSendAttemptCreated(SendAttemptCreatedEvent $event): void
    {
        $attempt = $event->sendAttempt;

        $send = $attempt->getSend();
        $project = $send->getProject();
        $recipients = $this->sendRecipientService->getSendRecipientsBySendAttempt($attempt);

        foreach ($recipients as $recipient) {
            $event = match ($recipient->getStatus()) {
                SendRecipientStatus::ACCEPTED => WebhooksEventEnum::SEND_RECIPIENT_ACCEPTED,
                SendRecipientStatus::DEFERRED => WebhooksEventEnum::SEND_RECIPIENT_DEFERRED,
                SendRecipientStatus::BOUNCED => WebhooksEventEnum::SEND_RECIPIENT_BOUNCED,
                SendRecipientStatus::FAILED => WebhooksEventEnum::SEND_RECIPIENT_FAILED,
                default => null // at this point, only the above statuses should be possible
            };

            if ($event === null) {
                continue;
            }

            $this->createWebhookDelivery(
                $project,
                $event,
                function () use ($send, $attempt, $recipient) {
                    return (object)[
                        'send' => new SendObject($send),
                        'recipient' => new SendRecipientObject($recipient),
                        'attempt' => new SendAttemptObject($attempt),
                    ];
                }
            );
        }
    }

    #[AsEventListener]
    public function onSendRecipientSuppressed(SendRecipientSuppressedEvent $event): void
    {
        $sendRecipient = $event->getSendRecipient();
        $send = $sendRecipient->getSend();

        $this->createWebhookDelivery(
            $send->getProject(),
            WebhooksEventEnum::SEND_RECIPIENT_SUPPRESSED,
            fn() => (object)[
                'send' => new SendObject($send),
                'recipient' => new SendRecipientObject($sendRecipient),
                'suppression' => new SuppressionObject($event->getSuppression()),
            ]
        );
    }

    #[AsEventListener]
    public function onIncomingBounce(IncomingBounceEvent $event): void
    {
        $send = $event->send;

        $this->createWebhookDelivery(
            $send->getProject(),
            WebhooksEventEnum::SEND_RECIPIENT_BOUNCED,
            fn() => (object)[
                'send' => new SendObject($send),
                'recipient' => new SendRecipientObject($event->sendRecipient),
                'bounce' => $event->bounce,
            ]
        );
    }

    #[AsEventListener]
    public function onIncomingComplaint(IncomingComplaintEvent $event): void
    {
        $send = $event->send;

        $this->createWebhookDelivery(
            $send->getProject(),
            WebhooksEventEnum::SEND_RECIPIENT_COMPLAINED,
            fn() => (object)[
                'send' => new SendObject($send),
                'recipient' => new SendRecipientObject($event->sendRecipient),
                'complaint' => $event->complaint,
            ]
        );
    }

    #[AsEventListener]
    public function onDomainCreate(DomainCreatedEvent $event): void
    {
        $this->createWebhookDelivery(
            $event->domain->getProject(),
            WebhooksEventEnum::DOMAIN_CREATED,
            fn() => (object)['domain' => new DomainObject($event->domain)]
        );
    }

    #[AsEventListener]
    public function onDomainStatusChange(DomainStatusChangedEvent $event): void
    {
        $this->createWebhookDelivery(
            $event->domain->getProject(),
            WebhooksEventEnum::DOMAIN_STATUS_CHANGED,
            fn() => (object)[
                'domain' => new DomainObject($event->domain),
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'dkim_result' => $event->result
            ]
        );
    }

    #[AsEventListener]
    public function onDomainDelete(DomainDeletedEvent $event): void
    {
        $this->createWebhookDelivery(
            $event->domain->getProject(),
            WebhooksEventEnum::DOMAIN_DELETED,
            fn() => (object)['domain' => new DomainObject($event->domain)]
        );
    }

    #[AsEventListener]
    public function onSuppressionCreate(SuppressionCreatedEvent $event): void
    {
        $this->createWebhookDelivery(
            $event->suppression->getProject(),
            WebhooksEventEnum::SUPPRESSION_CREATED,
            fn() => (object)['suppression' => new SuppressionObject($event->suppression)]
        );
    }

    #[AsEventListener]
    public function onSuppressionDelete(SuppressionDeletedEvent $event): void
    {
        $this->createWebhookDelivery(
            $event->suppression->getProject(),
            WebhooksEventEnum::SUPPRESSION_DELETED,
            fn() => (object)['suppression' => new SuppressionObject($event->suppression)]
        );
    }
}
