<?php

namespace App\Tests\Api\Console\Webhook;

use App\Api\Console\Controller\ApiKeyController;
use App\Api\Console\Controller\WebhookController;
use App\Api\Console\Object\ApiKeyObject;
use App\Api\Console\Object\WebhookObject;
use App\Entity\Type\ApiKeyScope;
use App\Service\ApiKey\ApiKeyService;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookService::class)]
#[CoversClass(WebhookObject::class)]
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
                    'send.delivered'
                ],
            ]
        );
        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertArrayHasKey('id', $content);
        $this->assertArrayHasKey('url', $content);
        $this->assertArrayHasKey('description', $content);
        $this->assertSame('https://example.com/webhook', $content['url']);
        $this->assertSame('Test Webhook', $content['description']);
        $this->assertArrayHasKey('events', $content);
        $this->assertIsArray($content['events']);
        $this->assertContains('send.delivered', $content['events']);
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