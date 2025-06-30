<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Idempotency\IdempotencyListener;
use App\Entity\ApiIdempotencyRecord;
use App\Service\Idempotency\IdempotencyService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiIdempotencyRecordFactory;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IdempotencyListener::class)]
#[CoversClass(IdempotencyService::class)]
class IdempotencyTest extends WebTestCase
{

    public function test_idempotency_from_storage(): void
    {

        $project = ProjectFactory::createOne();

        $idempotencyRecord = ApiIdempotencyRecordFactory::createOne([
            'project' => $project,
            'idempotency_key' => 'idempotency-key-123',
            'endpoint' => '/api/console/sends',
            'response' => ['status' => 'ok-idm'],
            'status_code' => 200,
        ]);

        $this->consoleApi(
            $project,
            'POST',
            '/sends',
            server: [
                'HTTP_X_IDEMPOTENCY_KEY' => 'idempotency-key-123',
            ],
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(['status' => 'ok-idm'], $json);

    }

    public function test_idempotency_response_saved(): void
    {

        $project = ProjectFactory::createOne();

        QueueFactory::createTransactional();
        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'dkim_verified' => true,
        ]);

        $this->consoleApi(
            $project,
            'POST',
            '/sends',
            data: [
                'from' => 'test@hyvor.com',
                'to' => 'test@example.com',
                'body_text' => 'Test email',
            ],
            server: [
                'HTTP_X_IDEMPOTENCY_KEY' => 'idempotency-key-123',
            ],
        );

        $this->assertResponseStatusCodeSame(200);

        $idempotencyRecords = $this->em
            ->getRepository(ApiIdempotencyRecord::class)
            ->findBy(['project' => $project->getId()]);

        $this->assertCount(1, $idempotencyRecords);

        $record = $idempotencyRecords[0];
        $this->assertSame('idempotency-key-123', $record->getIdempotencyKey());
        $this->assertSame('/api/console/sends', $record->getEndpoint());
        $this->assertSame(200, $record->getStatusCode());
        $this->assertArrayHasKey('message_id', $record->getResponse());

    }

}