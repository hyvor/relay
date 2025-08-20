<?php

namespace App\Tests\Service\Webhook;

use App\Entity\Project;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Entity\Webhook;
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
        $webhook1 = WebhookFactory::createOne(
            [
                'project' => $project,
                'events' => [WebhooksEventEnum::DOMAIN_CREATED],
                'url' => 'https://example.com/webhook1'
            ]
        );
        // selected, one of multiple
        $webhook2 = WebhookFactory::createOne(
            ['project' => $project, 'events' => [WebhooksEventEnum::DOMAIN_CREATED, WebhooksEventEnum::DOMAIN_VERIFIED]]
        );
        // not selected, other events
        $webhook3 = WebhookFactory::createOne(['project' => $project, 'events' => [WebhooksEventEnum::DOMAIN_DELETED]]);
        // not selected, other project
        $webhook4 = WebhookFactory::createOne(['events' => [WebhooksEventEnum::DOMAIN_CREATED]]);

        $this->ed->dispatch(new DomainCreatedEvent($domain));

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

    private const string WEBHOOK_URL = 'https://webhook.com/webhook';

    private function createWebhook(Project $project, WebhooksEventEnum $event): Webhook
    {
        return WebhookFactory::createOne([
            'project' => $project,
            'events' => [$event],
            'url' => self::WEBHOOK_URL
        ]);
    }

    /**
     * @param callable(array<mixed>): void|null $payloadValidator
     */
    private function assertWebhookDeliveryCreated(
        Project $project,
        WebhooksEventEnum $webhookEvent,
        ?callable $payloadValidator = null
    ): void {
        $deliveries = $this->em->getRepository(WebhookDelivery::class)->findAll();

        $this->assertCount(1, $deliveries);

        $delivery = $deliveries[0];
        $this->assertInstanceOf(WebhookDelivery::class, $delivery);
        $this->assertSame($webhookEvent, $delivery->getEvent());
        $this->assertSame($project->getId(), $delivery->getWebhook()->getProject()->getId());
        $this->assertSame(self::WEBHOOK_URL, $delivery->getUrl());

        $requestBody = json_decode($delivery->getRequestBody(), true);
        $this->assertIsArray($requestBody);

        $this->assertArrayHasKey('event', $requestBody);
        $this->assertSame($webhookEvent->value, $requestBody['event']);

        $payload = $requestBody['payload'];
        $this->assertIsArray($payload);

        if ($payloadValidator) {
            $payloadValidator($payload);
        }
    }

    #[TestWith([SendAttemptStatus::ACCEPTED, WebhooksEventEnum::SEND_ACCEPTED])]
    #[TestWith([SendAttemptStatus::DEFERRED, WebhooksEventEnum::SEND_DEFERRED])]
    #[TestWith([SendAttemptStatus::BOUNCED, WebhooksEventEnum::SEND_BOUNCED])]
    public function test_creates_delivery_for_sent_attempt(
        SendAttemptStatus $sendAttemptStatus,
        WebhooksEventEnum $webhookEvent
    ): void {
        $project = ProjectFactory::createOne();
        $this->createWebhook($project, $webhookEvent);

        $send = SendFactory::createOne(['project' => $project]);
        $attempt = SendAttemptFactory::createOne(['status' => $sendAttemptStatus, 'send' => $send]);

        $this->ed->dispatch(new SendAttemptCreatedEvent($attempt));

        $this->assertWebhookDeliveryCreated(
            $project,
            $webhookEvent,
            function (array $payload) use ($send, $attempt) {
                $this->assertIsArray($payload['send']);
                $this->assertSame($send->getId(), $payload['send']['id']);

                $this->assertIsArray($payload['attempt']);
                $this->assertSame($attempt->getId(), $payload['attempt']['id']);
            }
        );
    }

    public function test_creates_delivery_for_domain_created_event(): void
    {
        $project = ProjectFactory::createOne();
        $webhook = $this->createWebhook($project, WebhooksEventEnum::DOMAIN_CREATED);
        $domain = DomainFactory::createOne(['project' => $project]);
        $this->ed->dispatch(new DomainCreatedEvent($domain));

        $this->assertWebhookDeliveryCreated(
            $project,
            WebhooksEventEnum::DOMAIN_CREATED,
            function (array $payload) use ($domain) {
                $this->assertIsArray($payload['domain']);
                $this->assertSame($domain->getId(), $payload['domain']['id']);
            }
        );
    }

}