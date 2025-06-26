<?php

namespace App\Tests\Api\Console\ApiKey;

use App\Api\Console\Controller\ApiKeyController;
use App\Api\Console\Object\ApiKeyObject;
use App\Entity\ApiKey;
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
class DeleteApiKeyTest extends WebTestCase
{
    public function test_delete_api_key(): void
    {
        $project = ProjectFactory::createOne();

        $apiKey = ApiKeyFactory::createOne(
            [
                'project' => $project,
                'is_enabled' => true,
            ]
        );

        $apiKeyId = $apiKey->getId();

        $response = $this->consoleApi(
            $project,
            'DELETE',
            '/api-keys/' . $apiKey->getId()
        );

        $this->assertSame(200, $response->getStatusCode());

        $deletedApiKey = $this->em->getRepository(ApiKey::class)->find($apiKeyId);
        $this->assertNull($deletedApiKey);
    }

    public function test_delete_non_existent_api_key(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'DELETE',
            '/api-keys/999999' // Assuming this ID does not exist
        );

        $this->assertSame(404, $response->getStatusCode());
    }
}