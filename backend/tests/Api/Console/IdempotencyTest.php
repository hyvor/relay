<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Idempotency\IdempotencyListener;
use App\Api\Console\RateLimit\RateLimit;
use App\Entity\ApiIdempotencyRecord;
use App\Entity\Type\DomainStatus;
use App\Service\App\RateLimit\RateLimiterProvider;
use App\Service\Idempotency\IdempotencyService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiIdempotencyRecordFactory;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(IdempotencyListener::class)]
#[CoversClass(IdempotencyService::class)]
class IdempotencyTest extends WebTestCase
{
    public function test_idempotency_from_storage(): void
    {
        $project = ProjectFactory::createOne();

        $idempotencyRecord = ApiIdempotencyRecordFactory::createOne([
            "project" => $project,
            "idempotency_key" => "idempotency-key-123",
            "endpoint" => "/api/console/sends",
            "response" => ["status" => "ok-idm"],
            "status_code" => 200,
        ]);

        $this->consoleApi(
            $project,
            "POST",
            "/sends",
            server: [
                "HTTP_X_IDEMPOTENCY_KEY" => "idempotency-key-123",
            ]
        );

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHasHeader('X-Idempotency-Short-Circuit', 'true');

        $json = $this->getJson();
        $this->assertSame(["status" => "ok-idm"], $json);
    }

    public function test_idempotency_response_saved(): void
    {
        $project = ProjectFactory::createOne();

        QueueFactory::createTransactional();
        DomainFactory::createOne([
            "project" => $project,
            "domain" => "hyvor.com",
            'status' => DomainStatus::ACTIVE
        ]);

        $this->consoleApi(
            $project,
            "POST",
            "/sends",
            data: [
                "from" => "test@hyvor.com",
                "to" => "test@example.com",
                "body_text" => "Test email",
            ],
            server: [
                "HTTP_X_IDEMPOTENCY_KEY" => "idempotency-key-123",
            ]
        );

        $this->assertResponseStatusCodeSame(200);

        $idempotencyRecords = $this->em
            ->getRepository(ApiIdempotencyRecord::class)
            ->findBy(["project" => $project->getId()]);

        $this->assertCount(1, $idempotencyRecords);

        $record = $idempotencyRecords[0];
        $this->assertSame("idempotency-key-123", $record->getIdempotencyKey());
        $this->assertSame("/api/console/sends", $record->getEndpoint());
        $this->assertSame(200, $record->getStatusCode());
        $this->assertArrayHasKey("message_id", $record->getResponse());
    }

    public function test_idempotency_not_supported_endpoint_throws_exception(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            "GET",
            "/api-keys", // This endpoint doesn't have IdempotencySupported attribute
            server: [
                "HTTP_X_IDEMPOTENCY_KEY" => "idempotency-key-123",
            ]
        );

        $this->assertResponseStatusCodeSame(400);

        $json = $this->getJson();
        $this->assertSame(
            'This endpoint does not support idempotency. Retry without the "X-Idempotency-Key" header.',
            $json["message"]
        );
    }

    #[TestWith([500])]
    #[TestWith([429])]
    #[TestWith([200, true])]
    public function test_does_not_save_when_response_code_is_wrong(int $statusCode, bool $notJson = false): void
    {
        /** @var IdempotencyListener $listener */
        $listener = $this->container->get(IdempotencyListener::class);
        /** @var KernelInterface $kernel */
        $kernel = self::$kernel;

        $request = new Request();
        $request->attributes->set('idempotency_key_in_request', 'somekey');

        $responseEvent = new ResponseEvent(
            $kernel,
            $request,
            0,
            $notJson ?
                new Response('', $statusCode) :
                new JsonResponse([], $statusCode)
        );

        $listener->onResponse($responseEvent);

        $idempotencyRecords = $this->em
            ->getRepository(ApiIdempotencyRecord::class)
            ->findAll();
        $this->assertCount(0, $idempotencyRecords);
    }

    public function test_runs_rate_limits_before_idempotency(): void
    {
        $rateLimit = new RateLimit();
        /** @var RateLimiterProvider $rateLimiterProvider */
        $rateLimiterProvider = $this->getContainer()->get(RateLimiterProvider::class);

        $limiter = $rateLimiterProvider->rateLimiter($rateLimit->session(), "user:1");
        $limiter->consume(60);
        $limiter->consume(60);

        $project = ProjectFactory::createOne(['hyvor_user_id' => 1]);

        $idempotencyRecord = ApiIdempotencyRecordFactory::createOne([
            "project" => $project,
            "idempotency_key" => "idempotency-key-123",
            "endpoint" => "/api/console/sends",
            "response" => ["status" => "ok-idm"],
            "status_code" => 200,
        ]);

        $this->consoleApi(
            $project,
            "POST",
            "/sends",
            server: [
                "HTTP_X_IDEMPOTENCY_KEY" => "idempotency-key-123",
            ],
            useSession: true
        );

        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseNotHasHeader('X-Idempotency-Short-Circuit');
    }

}
