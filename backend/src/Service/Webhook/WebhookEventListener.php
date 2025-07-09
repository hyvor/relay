<?php

namespace App\Service\Webhook;

use App\Api\Console\Object\DomainObject;
use App\Entity\Project;
use App\Entity\Type\WebhooksEventEnum;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Service\Domain\Event\DomainVerifiedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class WebhookEventListener
{

    /**
     * @param callable(): object $objectFactory
     */
    private function sendWebhooks(Project $project, WebhooksEventEnum $eventType, callable $objectFactory): void
    {

        $webhooks =

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