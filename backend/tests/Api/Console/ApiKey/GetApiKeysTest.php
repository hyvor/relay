<?php

namespace App\Tests\Api\Console\ApiKey;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\ApiKeyController;
use App\Api\Console\Object\ApiKeyObject;
use App\Entity\Type\ApiKeyScope;
use App\Service\ApiKey\ApiKeyService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ApiKeyController::class)]
#[CoversClass(ApiKeyService::class)]
#[CoversClass(ApiKeyScope::class)]
#[CoversClass(ApiKeyObject::class)]
class GetApiKeysTest extends WebTestCase
{
    public function test_get_api_keys(): void
    {
        $project = ProjectFactory::createOne();

        $apiKeys = ApiKeyFactory::createMany(4, [
            'project' => $project,
            'scopes' => ['sends.sends']
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/api-keys'
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertCount(5, $content); // Count the API Created in consoleApi() and the 4 created API keys
    }

    public function test_get_api_keys_empty(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/api-keys'
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertIsArray($content);
        $this->assertCount(1, $content); // Count the API Created in consoleApi()
    }
}
