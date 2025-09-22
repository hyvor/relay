<?php

namespace App\Service\Webhook;

use App\Api\Console\Object\DomainObject;
use App\Api\Console\Object\SendAttemptObject;
use App\Api\Console\Object\SendObject;
use App\Api\Console\Object\SendRecipientObject;
use App\Api\Console\Object\SuppressionObject;
use App\Entity\Project;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Service\Domain\Event\DomainStatusChangedEvent;
use App\Service\IncomingMail\Event\IncomingBounceEvent;
use App\Service\IncomingMail\Event\IncomingComplaintEvent;
use App\Service\Send\Event\SuppressedRecipientCreatedEvent;
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
     * @param callable(): (object|object[]) $objectFactory
     */
    private function createWebhookDeliveries(
        Project $project,
        WebhooksEventEnum $eventType,
        callable $objectFactory
    ): void {
        $webhooks = $this->webhookService->getWebhooksForEvent($project, $eventType);

        if (count($webhooks) === 0) {
            return;
        }

        $objects = $objectFactory();
        if (!is_array($objects)) {
            $objects = [$objects];
        }

        foreach ($webhooks as $webhook) {
            foreach ($objects as $object) {
                $this->webhookService->createWebhookDelivery(
                    $webhook,
                    $eventType,
                    $object
                );
            }
        }
    }

    #[AsEventListener]
    public function onSendAttemptCreated(SendAttemptCreatedEvent $event): void
    {
        $attempt = $event->sendAttempt;
        $event = match ($attempt->getStatus()) {
            SendAttemptStatus::ACCEPTED => WebhooksEventEnum::SEND_RECIPIENT_ACCEPTED,
            SendAttemptStatus::DEFERRED => WebhooksEventEnum::SEND_RECIPIENT_DEFERRED,
            SendAttemptStatus::BOUNCED => WebhooksEventEnum::SEND_RECIPIENT_BOUNCED,
            SendAttemptStatus::FAILED => WebhooksEventEnum::SEND_RECIPIENT_FAILED,
        };

        $send = $attempt->getSend();
        $project = $send->getProject();

        $this->createWebhookDeliveries(
            $project,
            $event,
            function () use ($send, $attempt) {
                $recipients = $this->sendRecipientService->getSendRecipientsBySendAttempt($attempt);
                return array_map(fn($recipient) => (object)[
                    'send' => new SendObject($send),
                    'recipient' => new SendRecipientObject($recipient),
                    'attempt' => new SendAttemptObject($attempt),
                ], $recipients);
            }
        );
    }

    #[AsEventListener]
    public function onSuppressedRecipientCreated(SuppressedRecipientCreatedEvent $event): void
    {
        $sendRecipient = $event->sendRecipient;
        $send = $sendRecipient->getSend();

        $this->createWebhookDeliveries(
            $send->getProject(),
            WebhooksEventEnum::SEND_RECIPIENT_FAILED,
            fn() => (object)[
                'send' => new SendObject($send),
                'recipient' => new SendRecipientObject($sendRecipient),
                'attempt' => null,
            ]
        );
    }

    #[AsEventListener]
    public function onIncomingBounce(IncomingBounceEvent $event): void
    {
        $send = $event->send;

        $this->createWebhookDeliveries(
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

        $this->createWebhookDeliveries(
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
        $this->createWebhookDeliveries(
            $event->domain->getProject(),
            WebhooksEventEnum::DOMAIN_CREATED,
            fn() => (object)['domain' => new DomainObject($event->domain)]
        );
    }

    #[AsEventListener]
    public function onDomainStatusChange(DomainStatusChangedEvent $event): void
    {
        $this->createWebhookDeliveries(
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
        $this->createWebhookDeliveries(
            $event->domain->getProject(),
            WebhooksEventEnum::DOMAIN_DELETED,
            fn() => (object)['domain' => new DomainObject($event->domain)]
        );
    }

    #[AsEventListener]
    public function onSuppressionCreate(SuppressionCreatedEvent $event): void
    {
        $this->createWebhookDeliveries(
            $event->suppression->getProject(),
            WebhooksEventEnum::SUPPRESSION_CREATED,
            fn() => (object)['suppression' => new SuppressionObject($event->suppression)]
        );
    }

    #[AsEventListener]
    public function onSuppressionDelete(SuppressionDeletedEvent $event): void
    {
        $this->createWebhookDeliveries(
            $event->suppression->getProject(),
            WebhooksEventEnum::SUPPRESSION_DELETED,
            fn() => (object)['suppression' => new SuppressionObject($event->suppression)]
        );
    }
}
