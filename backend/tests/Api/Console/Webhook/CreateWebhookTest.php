<?php

namespace App\Tests\Api\Console\Webhook;

use App\Api\Console\Controller\WebhookController;
use App\Api\Console\Input\CreateWebhookInput;
use App\Api\Console\Object\WebhookObject;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookService::class)]
#[CoversClass(WebhookObject::class)]
#[CoversClass(CreateWebhookInput::class)]
class CreateWebhookTest extends WebTestCase
{
    public function test_create_webhook(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/webhooks',
            [
                'url' => 'https://example.com/webhook',
                'description' => 'Test Webhook',
                'events' => [
                    'send.recipient.accepted'
                ],
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertArrayHasKey('webhook', $content);
        $this->assertArrayHasKey('key', $content);
        $webhookContent = $content['webhook'];
        $this->assertArrayHasKey('id', $webhookContent);
        $this->assertArrayHasKey('url', $webhookContent);
        $this->assertArrayHasKey('description', $webhookContent);
        $this->assertSame('https://example.com/webhook', $webhookContent['url']);
        $this->assertSame('Test Webhook', $webhookContent['description']);
        $this->assertArrayHasKey('events', $webhookContent);
        $this->assertIsArray($content['events']);
        $this->assertContains('send.recipient.accepted', $webhookContent['events']);
    }

    public function test_create_webhook_with_invalid_url(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/webhooks',
            [
                'url' => 'invalid-url',
                'description' => 'Test Webhook with Invalid URL',
            ]
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertHasViolation('url', 'This value is not a valid URL');
    }
}
