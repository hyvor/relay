<?php

namespace App\Tests\Service\Webhook;

use App\Api\Console\Object\BounceObject;
use App\Api\Console\Object\ComplaintObject;
use App\Entity\Project;
use App\Entity\Type\DomainStatus;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Entity\Webhook;
use App\Entity\WebhookDelivery;
use App\Service\Domain\DkimVerificationResult;
use App\Service\Domain\Event\DomainCreatedEvent;
use App\Service\Domain\Event\DomainDeletedEvent;
use App\Service\Domain\Event\DomainStatusChangedEvent;
use App\Service\IncomingMail\Event\IncomingBounceEvent;
use App\Service\IncomingMail\Event\IncomingComplaintEvent;
use App\Service\Send\Event\SendRecipientSuppressedEvent;
use App\Service\SendAttempt\Event\SendAttemptCreatedEvent;
use App\Service\Suppression\Event\SuppressionCreatedEvent;
use App\Service\Suppression\Event\SuppressionDeletedEvent;
use App\Service\Webhook\WebhookEventListener;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendAttemptRecipientFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use App\Tests\Factory\SuppressionFactory;
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
            [
                'project' => $project,
                'events' => [WebhooksEventEnum::DOMAIN_CREATED, WebhooksEventEnum::DOMAIN_STATUS_CHANGED]
            ]
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
        ?callable $payloadValidator = null,
        int $count = 1
    ): void {
        $deliveries = $this->em->getRepository(WebhookDelivery::class)->findAll();

        $this->assertCount($count, $deliveries);

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

    #[TestWith([SendRecipientStatus::ACCEPTED, WebhooksEventEnum::SEND_RECIPIENT_ACCEPTED])]
    #[TestWith([SendRecipientStatus::DEFERRED, WebhooksEventEnum::SEND_RECIPIENT_DEFERRED])]
    #[TestWith([SendRecipientStatus::BOUNCED, WebhooksEventEnum::SEND_RECIPIENT_BOUNCED])]
    #[TestWith([SendRecipientStatus::FAILED, WebhooksEventEnum::SEND_RECIPIENT_FAILED])]
    public function test_creates_delivery_for_sent_attempt(
        SendRecipientStatus $sendRecipientStatus,
        WebhooksEventEnum $webhookEvent
    ): void {
        $project = ProjectFactory::createOne();
        $this->createWebhook($project, $webhookEvent);

        $send = SendFactory::createOne(['project' => $project]);

        $recipient = SendRecipientFactory::createOne([
            'send' => $send,
            'status' => $sendRecipientStatus,
            'address' => 'nadil@example.com'
        ]);
        SendRecipientFactory::createOne([
            'send' => $send,
            'address' => 'supun@example.com'
        ]);

        $attempt = SendAttemptFactory::createOne([
            'send' => $send,
        ]);

        SendAttemptRecipientFactory::createOne([
            'send_attempt' => $attempt,
            'recipient_status' => $sendRecipientStatus,
            'send_recipient_id' => $recipient->getId(),
        ]);

        $this->ed->dispatch(new SendAttemptCreatedEvent($attempt));

        $this->assertWebhookDeliveryCreated(
            $project,
            $webhookEvent,
            function (array $payload) use ($send, $recipient, $attempt) {
                $this->assertIsArray($payload['send']);
                $this->assertSame($send->getId(), $payload['send']['id']);

                $this->assertIsArray($payload['recipient']);
                $this->assertSame($recipient->getId(), $payload['recipient']['id']);

                $this->assertIsArray($payload['attempt']);
                $this->assertSame($attempt->getId(), $payload['attempt']['id']);
            },
            1
        );
    }

    public function test_creates_delivery_for_send_recipient_bounced_event(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);
        $sendRecipient = SendRecipientFactory::createOne(['send' => $send]);
        $this->createWebhook($project, WebhooksEventEnum::SEND_RECIPIENT_BOUNCED);
        $bounce = new BounceObject('Test bounce', '5.1.1');
        $this->ed->dispatch(new IncomingBounceEvent($send, $sendRecipient, $bounce));

        $this->assertWebhookDeliveryCreated(
            $project,
            WebhooksEventEnum::SEND_RECIPIENT_BOUNCED,
            function (array $payload) use ($send, $sendRecipient) {
                $this->assertIsArray($payload['send']);
                $this->assertSame($send->getId(), $payload['send']['id']);

                $this->assertIsArray($payload['recipient']);
                $this->assertSame($sendRecipient->getId(), $payload['recipient']['id']);

                $this->assertIsArray($payload['bounce']);
                $this->assertSame('Test bounce', $payload['bounce']['text']);
                $this->assertSame('5.1.1', $payload['bounce']['status']);
            }
        );
    }

    public function test_creates_delivery_for_send_recipient_complained_event(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);
        $sendRecipient = SendRecipientFactory::createOne(['send' => $send]);
        $this->createWebhook($project, WebhooksEventEnum::SEND_RECIPIENT_COMPLAINED);
        $complaint = new ComplaintObject('Test complaint', 'spam');
        $this->ed->dispatch(new IncomingComplaintEvent($send, $sendRecipient, $complaint));

        $this->assertWebhookDeliveryCreated(
            $project,
            WebhooksEventEnum::SEND_RECIPIENT_COMPLAINED,
            function (array $payload) use ($send, $sendRecipient) {
                $this->assertIsArray($payload['send']);
                $this->assertSame($send->getId(), $payload['send']['id']);

                $this->assertIsArray($payload['recipient']);
                $this->assertSame($sendRecipient->getId(), $payload['recipient']['id']);

                $this->assertIsArray($payload['complaint']);
                $this->assertSame('Test complaint', $payload['complaint']['text']);
                $this->assertSame('spam', $payload['complaint']['feedback_type']);
            }
        );
    }

    public function test_creates_delivery_for_suppressed_send_recipients(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project]);
        $sendRecipient = SendRecipientFactory::createOne(['send' => $send]);
        $this->createWebhook($project, WebhooksEventEnum::SEND_RECIPIENT_SUPPRESSED);
        $suppression = SuppressionFactory::createOne(['project' => $project, 'email' => 'supun@hyvor.com']);
        $this->ed->dispatch(new SendRecipientSuppressedEvent($sendRecipient, $suppression));

        $this->assertWebhookDeliveryCreated(
            $project,
            WebhooksEventEnum::SEND_RECIPIENT_SUPPRESSED,
            function (array $payload) use ($send, $sendRecipient) {
                $this->assertIsArray($payload['send']);
                $this->assertSame($send->getId(), $payload['send']['id']);

                $this->assertIsArray($payload['recipient']);
                $this->assertSame($sendRecipient->getId(), $payload['recipient']['id']);

                $this->assertIsArray($payload['suppression']);
                $this->assertSame('supun@hyvor.com', $payload['suppression']['email']);
            }
        );
    }


    public function test_creates_delivery_for_domain_created_event(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne(['project' => $project]);
        $this->createWebhook($project, WebhooksEventEnum::DOMAIN_CREATED);
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

    public function test_creates_delivery_for_domain_status_changed_event(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne(['project' => $project]);
        $this->createWebhook($project, WebhooksEventEnum::DOMAIN_STATUS_CHANGED);

        $result = new DkimVerificationResult();
        $result->verified = true;
        $result->checkedAt = new \DateTimeImmutable();

        $this->ed->dispatch(
            new DomainStatusChangedEvent(
                $domain,
                DomainStatus::PENDING,
                DomainStatus::ACTIVE,
                $result
            )
        );

        $this->assertWebhookDeliveryCreated(
            $project,
            WebhooksEventEnum::DOMAIN_STATUS_CHANGED,
            function (array $payload) use ($domain) {
                $this->assertIsArray($payload['domain']);
                $this->assertSame($domain->getId(), $payload['domain']['id']);
                $this->assertSame(DomainStatus::PENDING->value, $payload['old_status']);
                $this->assertSame(DomainStatus::ACTIVE->value, $payload['new_status']);
                $this->assertIsArray($payload['dkim_result']);
                $this->assertTrue($payload['dkim_result']['verified']);
            }
        );
    }

    public function test_creates_delivery_for_domain_deleted_event(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne(['project' => $project]);
        $this->createWebhook($project, WebhooksEventEnum::DOMAIN_DELETED);
        $this->ed->dispatch(new DomainDeletedEvent($domain));

        $this->assertWebhookDeliveryCreated(
            $project,
            WebhooksEventEnum::DOMAIN_DELETED,
            function (array $payload) use ($domain) {
                $this->assertIsArray($payload['domain']);
                $this->assertSame($domain->getId(), $payload['domain']['id']);
            }
        );
    }

    public function test_creates_delivery_for_suppression_created_event(): void
    {
        $project = ProjectFactory::createOne();
        $suppression = SuppressionFactory::createOne(['project' => $project]);
        $this->createWebhook($project, WebhooksEventEnum::SUPPRESSION_CREATED);
        $this->ed->dispatch(new SuppressionCreatedEvent($suppression));

        $this->assertWebhookDeliveryCreated(
            $project,
            WebhooksEventEnum::SUPPRESSION_CREATED,
            function (array $payload) use ($suppression) {
                $this->assertIsArray($payload['suppression']);
                $this->assertSame($suppression->getId(), $payload['suppression']['id']);
            }
        );
    }

    public function test_creates_delivery_for_suppression_deleted_event(): void
    {
        $project = ProjectFactory::createOne();
        $suppression = SuppressionFactory::createOne(['project' => $project]);
        $this->createWebhook($project, WebhooksEventEnum::SUPPRESSION_DELETED);
        $this->ed->dispatch(new SuppressionDeletedEvent($suppression));

        $this->assertWebhookDeliveryCreated(
            $project,
            WebhooksEventEnum::SUPPRESSION_DELETED,
            function (array $payload) use ($suppression) {
                $this->assertIsArray($payload['suppression']);
                $this->assertSame($suppression->getId(), $payload['suppression']['id']);
            }
        );
    }
}
