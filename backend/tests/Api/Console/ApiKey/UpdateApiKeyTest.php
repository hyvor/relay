<?php

namespace App\Tests\Api\Console\ApiKey;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\ApiKeyController;
use App\Api\Console\Input\UpdateApiKeyInput;
use App\Api\Console\Object\ApiKeyObject;
use App\Entity\ApiKey;
use App\Service\ApiKey\ApiKeyService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ApiKeyController::class)]
#[CoversClass(ApiKeyService::class)]
#[CoversClass(ApiKeyObject::class)]
#[CoversClass(UpdateApiKeyInput::class)]
class UpdateApiKeyTest extends WebTestCase
{
    public function test_update_api_key(): void
    {
        $project = ProjectFactory::createOne();

        $apiKey = ApiKeyFactory::createOne(
            [
                'project' => $project,
                'is_enabled' => true,

            ]
        );

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/api-keys/' . $apiKey->getId(),
            [
                'enabled' => false,
                'name' => 'Updated API Key',
                'scopes' => ['sends.read', 'sends.write', 'webhooks.read']
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertArrayHasKey('is_enabled', $content);
        $this->assertFalse($content['is_enabled']);
        $this->assertNull($content['key']);
        $this->assertArrayHasKey('scopes', $content);
        $this->assertIsArray($content['scopes']);
        $this->assertCount(3, $content['scopes']);
        $this->assertSame('Updated API Key', $content['name']);

        $apiKeyDb = $this->em->getRepository(ApiKey::class)->find($apiKey->getId());
        $this->assertNotNull($apiKeyDb);
        $this->assertFalse($apiKeyDb->getIsEnabled());
        $this->assertCount(3, $apiKeyDb->getScopes());
        $this->assertContains('sends.read', $apiKeyDb->getScopes());
        $this->assertContains('sends.write', $apiKeyDb->getScopes());
        $this->assertContains('webhooks.read', $apiKeyDb->getScopes());
        $this->assertSame('Updated API Key', $apiKeyDb->getName());
    }
}
