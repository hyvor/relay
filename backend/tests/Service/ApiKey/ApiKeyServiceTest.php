<?php

namespace App\Tests\Service\ApiKey;

use App\Entity\Project;
use App\Service\ApiKey\ApiKeyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[CoversClass(ApiKeyService::class)]
class ApiKeyServiceTest extends TestCase
{
    public function test_create_api_key_throws_when_sends_send_without_allowed_ips(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $service = new ApiKeyService($em);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('At least one allowed IP is required when the "sends.send" scope is enabled.');

        $service->createApiKey(new Project(), 'Test', ['sends.send'], []);
    }
}
