<?php

namespace App\Service\Webhook;

use App\Api\Console\Object\DomainObject;
use App\Api\Console\Object\SendAttemptObject;
use App\Api\Console\Object\SendObject;
use App\Entity\Project;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Service\Domain\Event\DomainVerifiedEvent;
use App\Service\Send\Event\SendAttemptCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class WebhookEventListener
{

    public function __construct(
        private WebhookService $webhookService,
    )
    {
    }

    /**
     * @param callable(): object $objectFactory
     */
    private function sendWebhooks(Project $project, WebhooksEventEnum $eventType, callable $objectFactory): void
    {

        $webhooks = $this->webhookService->getWebhooksForEvent($project, $eventType);

        foreach ($webhooks as $webhook) {
            $this->webhookService->createWebhookDelivery(
                $webhook,
                $eventType,
                $objectFactory()
            );
        }

    }

    #[AsEventListener]
    public function onSendAttemptCreated(SendAttemptCreatedEvent $sendAttempt): void
    {
        $attempt = $sendAttempt->sendAttempt;
        $event = match ($attempt->getStatus()) {
            SendAttemptStatus::ACCEPTED => WebhooksEventEnum::SEND_ACCEPTED,
            SendAttemptStatus::DEFERRED => WebhooksEventEnum::SEND_DEFERRED,
            SendAttemptStatus::BOUNCED => WebhooksEventEnum::SEND_BOUNCED,
        };

        $send = $attempt->getSend();

        $this->sendWebhooks(
            $send->getProject(),
            $event,
            fn() => (object) [
                'send' => new SendObject($send),
                'attempt' => new SendAttemptObject($attempt)
            ]
        );
    }

    #[AsEventListener]
    public function onDomainCreate(DomainCreatedEvent $event): void
    {
        $this->sendWebhooks(
            $event->domain->getProject(),
            WebhooksEventEnum::DOMAIN_CREATED,
            fn() => new DomainObject($event->domain)
        );
    }

    #[AsEventListener]
    public function onDomainVerified(DomainVerifiedEvent $event): void
    {
        //
    }

    #[AsEventListener]
    public function onDomainDeleted(DomainDeletedEvent $event): void
    {
        //
    }

}