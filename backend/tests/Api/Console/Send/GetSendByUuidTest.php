<?php

namespace App\Tests\Api\Console\Send;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\SendController;
use App\Api\Console\Object\SendObject;
use App\Service\Send\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Uid\Uuid;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendObject::class)]
class GetSendByUuidTest extends WebTestCase
{
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

        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/uuid/' . $send->getUuid(),
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();

        $this->assertArrayHasKey('id', $json);
        $this->assertSame($send->getId(), $json['id']);
    }

    public function test_get_specific_email_not_found(): void
    {
        $project = ProjectFactory::createOne();

        $uuid = Uuid::v4();
        $response = $this->consoleApi(
            $project,
            'GET',
            '/sends/uuid/' . $uuid,
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(404, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertSame("Send with UUID " . $uuid . " not found", $json['message']);


    }

    public function test_cannot_get_other_project_sends(): void
    {
        $project = ProjectFactory::createOne();
        $otherProject = ProjectFactory::createOne();

        $send = SendFactory::createOne(
            [
                'project' => $project,
            ]
        );

        $response = $this->consoleApi(
            $otherProject,
            'GET',
            '/sends/uuid/' . $send->getUuid(),
            scopes: [Scope::SENDS_READ]
        );

        $this->assertSame(400, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertSame("Send with UUID " . $send->getUuid() . " does not belong to project", $json['message']);
    }
}