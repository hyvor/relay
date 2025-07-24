<?php

namespace App\Tests\Api\Console\ApiKey;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\ApiKeyController;
use App\Api\Console\Input\CreateApiKeyInput;
use App\Api\Console\Object\ApiKeyObject;
use App\Service\ApiKey\ApiKeyService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ApiKeyController::class)]
#[CoversClass(ApiKeyService::class)]
#[CoversClass(Scope::class)]
#[CoversClass(CreateApiKeyInput::class)]
#[CoversClass(ApiKeyObject::class)]
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
                'scopes' => ['sends.read', 'sends.write', 'sends.send']
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();

        $this->assertArrayHasKey('key', $content);
        $this->assertNotNull($content['key']);
        $this->assertArrayHasKey('created_at', $content);
    }

    public function test_create_api_key_without_name(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'scopes' => ['sends.read', 'sends.write', 'sends.send']
            ]
        );

        $this->assertSame(422, $response->getStatusCode());

        $this->assertHasViolation('name', 'This value should not be blank.');
    }

    public function test_create_api_key_without_scope(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'Test name'
            ]
        );

        $this->assertSame(422, $response->getStatusCode());

        $this->assertHasViolation('scopes', 'This value should not be blank.');
    }

    public function test_create_api_key_invalid_scope(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'Test name',
                'scopes' => ['sends.read', 'sends.write', 'invalid_scope']
            ]
        );
        $this->assertSame(422, $response->getStatusCode());
        $this->assertHasViolation('scopes[2]', 'The value you selected is not a valid choice.');
    }

    public function test_create_api_key_reaching_limit(): void
    {
        $project = ProjectFactory::createOne();

        $apiKeys = ApiKeyFactory::createMany(5, [
            'project' => $project,
            'is_enabled' => true,
        ]);

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'Exceeding limit',
                'scopes' => ['sends.read', 'sends.write', 'sends.send']
            ]
        );

        $this->assertSame(400, $response->getStatusCode());
        $content = $this->getJson();
        $this->assertArrayHasKey('message', $content);
        $this->assertSame('You have reached the maximum number of API keys for this project.', $content['message']);
    }
}
