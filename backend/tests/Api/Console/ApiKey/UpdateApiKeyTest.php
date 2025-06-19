<?php

namespace App\Tests\Api\Console\ApiKey;

use App\Entity\ApiKey;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\ProjectFactory;

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
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertArrayHasKey('is_enabled', $content);
        $this->assertFalse($content['is_enabled']);
        $this->assertNull($content['key']);

        $apiKeyDb = $this->em->getRepository(ApiKey::class)->find($apiKey->getId());
        $this->assertNotNull($apiKeyDb);
        $this->assertFalse($apiKeyDb->getIsEnabled());
    }
}