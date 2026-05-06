<?php

namespace App\Tests\Api\Console\ApiKey;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\ApiKeyController;
use App\Api\Console\Input\UpdateApiKeyInput;
use App\Api\Console\Object\ApiKeyObject;
use App\Entity\ApiKey;
use App\Service\ApiKey\AllowedIp;
use App\Service\ApiKey\ApiKeyService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\ProjectFactory;
use App\Validator\AllowedIpsConstraint;
use App\Validator\AllowedIpsConstraintValidator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ApiKeyController::class)]
#[CoversClass(ApiKeyService::class)]
#[CoversClass(ApiKeyObject::class)]
#[CoversClass(UpdateApiKeyInput::class)]
#[CoversClass(AllowedIp::class)]
#[CoversClass(AllowedIpsConstraint::class)]
#[CoversClass(AllowedIpsConstraintValidator::class)]
class UpdateApiKeyTest extends WebTestCase
{
    public function test_update_api_key(): void
    {
        $project = ProjectFactory::createOne();

        $apiKey = ApiKeyFactory::createOne(
            [
                'project' => $project,
                'is_enabled' => true,
                'allowed_ips' => ['203.0.113.5'],
            ]
        );

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/api-keys/' . $apiKey->getId(),
            [
                'is_enabled' => false,
                'name' => 'Updated API Key',
                'scopes' => ['sends.read', 'sends.send', 'webhooks.read']
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
        $this->assertContains('sends.send', $apiKeyDb->getScopes());
        $this->assertContains('webhooks.read', $apiKeyDb->getScopes());
        $this->assertSame('Updated API Key', $apiKeyDb->getName());
    }

    public function test_update_allowed_ips(): void
    {
        $project = ProjectFactory::createOne();

        $apiKey = ApiKeyFactory::createOne([
            'project' => $project,
            'scopes' => ['sends.send'],
            'allowed_ips' => ['203.0.113.5'],
        ]);

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/api-keys/' . $apiKey->getId(),
            [
                'allowed_ips' => ['198.51.100.0/24', '2001:db8::/64'],
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $apiKeyDb = $this->em->getRepository(ApiKey::class)->find($apiKey->getId());
        $this->assertNotNull($apiKeyDb);
        $this->assertSame(['198.51.100.0/24', '2001:db8::/64'], $apiKeyDb->getAllowedIps());
    }

    public function test_update_rejects_clearing_ips_when_sends_send_active(): void
    {
        $project = ProjectFactory::createOne();

        $apiKey = ApiKeyFactory::createOne([
            'project' => $project,
            'scopes' => ['sends.send'],
            'allowed_ips' => ['203.0.113.5'],
        ]);

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/api-keys/' . $apiKey->getId(),
            [
                'allowed_ips' => [],
            ]
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            'At least one allowed IP is required when the "sends.send" scope is enabled.',
            $this->getJson()['message']
        );
    }

    public function test_update_rejects_adding_sends_send_without_ips(): void
    {
        $project = ProjectFactory::createOne();

        $apiKey = ApiKeyFactory::createOne([
            'project' => $project,
            'scopes' => ['sends.read'],
            'allowed_ips' => [],
        ]);

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/api-keys/' . $apiKey->getId(),
            [
                'scopes' => ['sends.send'],
            ]
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_update_rejects_invalid_ip(): void
    {
        $project = ProjectFactory::createOne();

        $apiKey = ApiKeyFactory::createOne([
            'project' => $project,
            'scopes' => ['sends.send'],
            'allowed_ips' => ['203.0.113.5'],
        ]);

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/api-keys/' . $apiKey->getId(),
            [
                'allowed_ips' => ['not-an-ip'],
            ]
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertHasViolation('allowed_ips[0]');
    }
}
