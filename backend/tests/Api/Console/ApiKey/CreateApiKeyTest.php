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

    public function test_create_api_key_without_name(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'scope' => ApiKeyScope::SEND_EMAIL
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

        $this->assertHasViolation('scope', 'This value should not be blank.');
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
                'scope' => 'invalid_scope'
            ]
        );

        $this->assertSame(422, $response->getStatusCode());

        $this->assertHasViolation('scope', 'This value should be of type full|send_email.');
    }
}