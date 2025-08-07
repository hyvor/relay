<?php

namespace App\Tests\Api\Console\Send;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\SendController;
use App\Api\Console\Object\SendAttemptObject;
use App\Api\Console\Object\SendObject;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SendController::class)]
#[CoversClass(SendObject::class)]
#[CoversClass(SendAttemptObject::class)]
class GetSendByIdTest extends WebTestCase
{

    public function test_fails_when_not_found(): void
    {
        $project = ProjectFactory::createOne();
        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/123',
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(404, $response->getStatusCode());
        $json = $this->getJson();
        $this->assertSame('Entity not found', $json['message']);
    }

    public function test_get_specific_email(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $send = SendFactory::createOne(
            [
                'project' => $project,
                'domain' => $domain,
                'queue' => $queue,
            ]
        );

        SendAttemptFactory::createOne([
            'send' => $send,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/' . $send->getId(),
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();

        $this->assertArrayHasKey('id', $json);
        $this->assertSame($send->getId(), $json['id']);

        $attempts = $json['attempts'];
        $this->assertIsArray($attempts);
        $this->assertCount(1, $attempts);
    }

}