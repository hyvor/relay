<?php

namespace App\Tests\Api\Console\Webhook;

use App\Api\Console\Controller\WebhookController;
use App\Api\Console\Object\WebhookObject;
use App\Entity\Type\WebhookDeliveryStatus;
use App\Service\Webhook\WebhookDeliveryService;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\WebhookDeliveryFactory;
use App\Tests\Factory\WebhookFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookService::class)]
#[CoversClass(WebhookDeliveryService::class)]
#[CoversClass(WebhookObject::class)]
class GetWebhookDeliveriesTest extends WebTestCase
{
    public function test_get_webhook_deliveries(): void
    {
        $project = ProjectFactory::createOne();

        $webhook = WebhookFactory::createOne(
            [
                'project' => $project,
                'url' => 'https://example.com/webhook',
                'description' => 'Test Webhook',
                'events' => ['send.delivered'],
            ]
        );

        $webhookDeliveries = WebhookDeliveryFactory::createMany(5,
        [
                'webhook' => $webhook,
                'status' => WebhookDeliveryStatus::PENDING,
            ]
        );

        $response = $this->consoleApi(
            $project,
            'GET',
            '/webhooks/deliveries'
        );
        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertCount(5, $content);

        foreach ($content as $key =>  $delivery) {
            $this->assertIsArray($delivery);
            $this->assertArrayHasKey('id', $delivery);
            $this->assertArrayHasKey('status', $delivery);
            $this->assertArrayHasKey('created_at', $delivery);
            $this->assertSame($webhookDeliveries[$key]->getId(), $delivery['id']);
            $this->assertSame(WebhookDeliveryStatus::PENDING->value, $delivery['status']);
        }
    }
}