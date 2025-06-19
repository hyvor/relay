<?php

namespace App\Tests\Api\Console\ApiKey;

use App\Entity\Type\ApiKeyScope;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;

class CreateApiKeyTest extends WebTestCase
{
    public function test_create_api_key(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'Test name',
                'scope' => ApiKeyScope::SEND_EMAIL
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();

        $this->assertArrayHasKey('key', $content);
        $this->assertArrayHasKey('created_at', $content);
    }
}