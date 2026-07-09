<?php

namespace App\Tests\Api\Console\ApiKey;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\ApiKeyController;
use App\Api\Console\Input\CreateApiKeyInput;
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
#[CoversClass(Scope::class)]
#[CoversClass(CreateApiKeyInput::class)]
#[CoversClass(ApiKeyObject::class)]
#[CoversClass(AllowedIp::class)]
#[CoversClass(AllowedIpsConstraint::class)]
#[CoversClass(AllowedIpsConstraintValidator::class)]
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
                'scopes' => ['sends.read', 'sends.send'],
                'allowed_ips' => ['203.0.113.5', '198.51.100.0/24'],
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();

        $this->assertArrayHasKey('key', $content);
        $this->assertNotNull($content['key']);
        $this->assertArrayHasKey('created_at', $content);
        $this->assertArrayHasKey('allowed_ips', $content);
        $this->assertSame(['203.0.113.5', '198.51.100.0/24'], $content['allowed_ips']);

        $apiKey = $this->em->getRepository(ApiKey::class)->findOneBy([
            'id' => $content['id'],
        ]);
        $this->assertNotNull($apiKey);
        $this->assertSame('Test name', $apiKey->getName());
        $this->assertSame(['sends.read', 'sends.send'], $apiKey->getScopes());
        $this->assertSame(['203.0.113.5', '198.51.100.0/24'], $apiKey->getAllowedIps());
    }

    public function test_create_api_key_without_allowed_ips_when_sends_send_selected(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'No IPs',
                'scopes' => ['sends.send'],
            ]
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertHasViolation(
            'allowed_ips',
            'At least one allowed IP is required when the "sends.send" scope is enabled.'
        );
    }

    public function test_create_api_key_without_allowed_ips_for_non_send_scope(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'Read-only',
                'scopes' => ['sends.read'],
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $content = $this->getJson();
        $this->assertSame([], $content['allowed_ips']);
    }

    public function test_create_api_key_rejects_too_broad_private_cidr(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'Private',
                'scopes' => ['sends.send'],
                'allowed_ips' => ['10.0.0.0/8'],
            ]
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertHasViolation('allowed_ips[0]', 'IPv4 CIDR prefix must be between');
    }

    public function test_create_api_key_rejects_too_broad_cidr(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'Too broad',
                'scopes' => ['sends.send'],
                'allowed_ips' => ['203.0.113.0/16'],
            ]
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertHasViolation('allowed_ips[0]', 'between /24 and /32');
    }

    public function test_create_api_key_accepts_ipv6_cidr(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'IPv6',
                'scopes' => ['sends.send'],
                'allowed_ips' => ['2001:db8::/64'],
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $content = $this->getJson();
        $this->assertSame(['2001:db8::/64'], $content['allowed_ips']);
    }

    public function test_create_api_key_without_name(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'scopes' => ['sends.read', 'sends.send']
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

        $apiKeys = ApiKeyFactory::createMany(10, [
            'project' => $project,
            'is_enabled' => true,
        ]);

        $response = $this->consoleApi(
            $project,
            'POST',
            '/api-keys',
            [
                'name' => 'Exceeding limit',
                'scopes' => ['sends.read', 'sends.send'],
                'allowed_ips' => ['203.0.113.5'],
            ]
        );

        $this->assertSame(400, $response->getStatusCode());
        $content = $this->getJson();
        $this->assertArrayHasKey('message', $content);
        $this->assertSame('You have reached the maximum number of API keys for this project.', $content['message']);
    }
}
