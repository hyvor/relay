<?php

namespace App\Tests\Service\Webhook;

use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Entity\WebhookDelivery;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Send\Event\SendAttemptCreatedEvent;
use App\Service\Webhook\WebhookEventListener;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\WebhookFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(WebhookEventListener::class)]
#[CoversClass(WebhookService::class)]
class WebhookEventListenerTest extends KernelTestCase
{

    public function test_gets_webhooks_correctly(): void
    {

        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne(['project' => $project]);

        // selected, only
        $webhook1 = WebhookFactory::createOne(['project' => $project, 'events' => [WebhooksEventEnum::DOMAIN_CREATED], 'url' => 'https://example.com/webhook1']);
        // selected, one of multiple
        $webhook2 = WebhookFactory::createOne(['project' => $project, 'events' => [WebhooksEventEnum::DOMAIN_CREATED, WebhooksEventEnum::DOMAIN_VERIFIED]]);
        // not selected, other events
        $webhook3 = WebhookFactory::createOne(['project' => $project, 'events' => [WebhooksEventEnum::DOMAIN_DELETED]]);
        // not selected, other project
        $webhook4 = WebhookFactory::createOne(['events' => [WebhooksEventEnum::DOMAIN_CREATED]]);

        $this->eventDispatcher->dispatch(new DomainCreatedEvent($domain));

        $deliveries = $this->em->getRepository(WebhookDelivery::class)->findAll();

        $this->assertCount(2, $deliveries);

        $delivery1 = $deliveries[0];
        $this->assertInstanceOf(WebhookDelivery::class, $delivery1);
        $this->assertSame($webhook1->getId(), $delivery1->getWebhook()->getId());
        $this->assertSame(WebhooksEventEnum::DOMAIN_CREATED, $delivery1->getEvent());
        $this->assertSame('https://example.com/webhook1', $delivery1->getUrl());

        $delivery2 = $deliveries[1];
        $this->assertInstanceOf(WebhookDelivery::class, $delivery2);
        $this->assertSame($webhook2->getId(), $delivery2->getWebhook()->getId());
        $this->assertSame(WebhooksEventEnum::DOMAIN_CREATED, $delivery2->getEvent());
        $this->assertSame($webhook2->getUrl(), $delivery2->getUrl());

    }

    #[TestWith([SendAttemptStatus::ACCEPTED, WebhooksEventEnum::SEND_ACCEPTED])]
    #[TestWith([SendAttemptStatus::DEFERRED, WebhooksEventEnum::SEND_DEFERRED])]
    #[TestWith([SendAttemptStatus::BOUNCED, WebhooksEventEnum::SEND_BOUNCED])]
    public function test_creates_delivery_for_sent_attempt(SendAttemptStatus $sendAttemptStatus, WebhooksEventEnum $webhookEvent): void
    {

        $project = ProjectFactory::createOne();
        $webhook = WebhookFactory::createOne([
            'project' => $project,
            'events' => [
                $webhookEvent
            ],
            'url' => 'https://example.com/webhook'
        ]);

        $send = SendFactory::createOne(['project' => $project]);
        $attempt = SendAttemptFactory::createOne(['status' => $sendAttemptStatus, 'send' => $send]);

        $this->eventDispatcher->dispatch(new SendAttemptCreatedEvent($attempt));

        $deliveries = $this->em->getRepository(WebhookDelivery::class)->findAll();

        $this->assertCount(1, $deliveries);

        $delivery = $deliveries[0];
        $this->assertInstanceOf(WebhookDelivery::class, $delivery);
        $this->assertSame($webhookEvent, $delivery->getEvent());
        $this->assertSame($attempt->getSend()->getProject()->getId(), $delivery->getWebhook()->getProject()->getId());
        $this->assertSame('https://example.com/webhook', $delivery->getUrl());

        $requestBody = json_decode($delivery->getRequestBody(), true);
        $this->assertIsArray($requestBody);
        $this->assertArrayHasKey('event', $requestBody);
        $this->assertSame($webhookEvent->value, $requestBody['event']);

        $payload = $requestBody['payload'];
        $this->assertIsArray($payload);
        $this->assertIsArray($payload['send']);
        $this->assertSame($send->getId(), $payload['send']['id']);

        $this->assertIsArray($payload['attempt']);
        $this->assertSame($attempt->getId(), $payload['attempt']['id']);

    }

}