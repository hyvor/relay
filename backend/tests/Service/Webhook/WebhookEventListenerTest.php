<?php

namespace App\Tests\Service\Webhook;

use App\Entity\Type\WebhooksEventEnum;
use App\Entity\WebhookDelivery;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Webhook\WebhookEventListener;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\WebhookFactory;
use PHPUnit\Framework\Attributes\CoversClass;

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

}