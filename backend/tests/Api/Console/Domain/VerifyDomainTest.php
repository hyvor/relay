<?php

namespace App\Tests\Api\Console\Domain;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Controller\DomainController;
use App\Api\Console\Object\DomainObject;
use App\Service\Domain\DkimVerificationResult;
use App\Service\Domain\DkimVerificationService;
use App\Service\Domain\DomainService;
use App\Service\Domain\Event\DomainVerifiedEvent;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(DomainController::class)]
#[CoversClass(DomainService::class)]
#[CoversClass(DomainObject::class)]
#[CoversClass(DomainVerifiedEvent::class)]
class VerifyDomainTest extends WebTestCase
{
    private MockObject&DkimVerificationService $dkimVerificationService;
    private TestEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = TestEventDispatcher::enable($this->container);
        $this->dkimVerificationService = $this->createMock(
            DkimVerificationService::class
        );
        $this->container->set(
            DkimVerificationService::class,
            $this->dkimVerificationService
        );
    }

    public function testVerifyDomainSuccess(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne([
            "project" => $project,
            "domain" => "example.com",
            "dkim_verified" => false,
            "dkim_checked_at" => null,
            "dkim_error_message" => null,
        ]);

        $verificationResult = new DkimVerificationResult();
        $verificationResult->verified = true;
        $verificationResult->checkedAt = new \DateTimeImmutable();
        $verificationResult->errorMessage = null;

        $this->dkimVerificationService
            ->expects($this->once())
            ->method("verify")
            ->willReturn($verificationResult);

        $response = $this->consoleApi(
            $project,
            "POST",
            "/domains/verify",
            data: [
                'id' => $domain->getId(),
            ],
            scopes: [Scope::DOMAINS_WRITE]
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->getJson();
        $this->assertArrayHasKey("domain", $responseData);
        $this->assertSame("example.com", $responseData["domain"]);
        $this->assertTrue($responseData["dkim_verified"]);
        $this->assertNotNull($responseData["dkim_checked_at"]);
        $this->assertNull($responseData["dkim_error_message"]);

        // Verify the domain was updated in the database
        $this->assertTrue($domain->getDkimVerified());
        $this->assertNotNull($domain->getDkimCheckedAt());
        $this->assertNull($domain->getDkimErrorMessage());

        $this->eventDispatcher->assertDispatched(DomainVerifiedEvent::class);
    }

    public function testVerifyDomainFailure(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne([
            "project" => $project,
            "domain" => "example.com",
            "dkim_verified" => false,
            "dkim_checked_at" => null,
            "dkim_error_message" => null,
        ]);

        $verificationResult = new DkimVerificationResult();
        $verificationResult->verified = false;
        $verificationResult->checkedAt = new \DateTimeImmutable();
        $verificationResult->errorMessage = "DNS query failed";

        $this->dkimVerificationService
            ->expects($this->once())
            ->method("verify")
            ->willReturn($verificationResult);

        $response = $this->consoleApi(
            $project,
            "POST",
            "/domains/verify",
            data: [
                'id' => $domain->getId(),
            ],
            scopes: [Scope::DOMAINS_WRITE]
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->getJson();
        $this->assertArrayHasKey("domain", $responseData);
        $this->assertSame("example.com", $responseData["domain"]);
        $this->assertFalse($responseData["dkim_verified"]);
        $this->assertNotNull($responseData["dkim_checked_at"]);
        $this->assertSame(
            "DNS query failed",
            $responseData["dkim_error_message"]
        );

        // Verify the domain was updated in the database
        $this->assertFalse($domain->getDkimVerified());
        $this->assertNotNull($domain->getDkimCheckedAt());
        $this->assertSame("DNS query failed", $domain->getDkimErrorMessage());

        $this->eventDispatcher->assertNotDispatched(DomainVerifiedEvent::class);
    }

    public function testVerifyDomainAlreadyVerified(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne([
            "project" => $project,
            "domain" => "example.com",
            "dkim_verified" => true,
            "dkim_checked_at" => new \DateTimeImmutable(),
            "dkim_error_message" => null,
        ]);

        // The verification service should not be called for already verified domains
        $this->dkimVerificationService
            ->expects($this->never())
            ->method("verify");

        $response = $this->consoleApi(
            $project,
            "POST",
            "/domains/verify",
            data: [
                'domain' => $domain->getDomain(),
            ],
            scopes: [Scope::DOMAINS_WRITE]
        );

        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $responseData = $this->getJson();
        $this->assertArrayHasKey("message", $responseData);
        $this->assertSame(
            "Domain is already verified",
            $responseData["message"]
        );

        $this->eventDispatcher->assertNotDispatched(DomainVerifiedEvent::class);
    }

    public function testVerifyDomainWithoutPermission(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne([
            "project" => $project,
            "domain" => "example.com",
            "dkim_verified" => false,
        ]);

        $response = $this->consoleApi(
            $project,
            "POST",
            "/domains/verify",
            data: [
                'id' => $domain->getId(),
            ],
            scopes: [Scope::DOMAINS_READ] // Wrong scope
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->eventDispatcher->assertNotDispatched(DomainVerifiedEvent::class);
    }

    public function testVerifyDomainNotFound(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            "POST",
            "/domains/verify",
            data: ['id' => 999999],
            scopes: [Scope::DOMAINS_WRITE]
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Domain not found', $this->getJson()['message']);

        $this->eventDispatcher->assertNotDispatched(DomainVerifiedEvent::class);
    }

    public function testVerifyDomainFromDifferentProject(): void
    {
        $project1 = ProjectFactory::createOne();
        $project2 = ProjectFactory::createOne();

        $domain = DomainFactory::createOne([
            "project" => $project2,
            "domain" => "example.com",
            "dkim_verified" => false,
        ]);

        $response = $this->consoleApi(
            $project1, // Different project
            "POST",
            "/domains/verify",
            data: [
                'id' => $domain->getId(),
            ],
            scopes: [Scope::DOMAINS_WRITE]
        );

        $this->assertResponseStatusCodeSame(400);
        $responseData = $this->getJson();
        $this->assertSame("Domain does not belong to the project", $responseData['message']);

        $this->eventDispatcher->assertNotDispatched(DomainVerifiedEvent::class);
    }

    public function testVerifyDomainRetryAfterFailure(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne([
            "project" => $project,
            "domain" => "example.com",
            "dkim_verified" => false,
            "dkim_checked_at" => new \DateTimeImmutable("-1 hour"),
            "dkim_error_message" => "Previous error",
        ]);

        $verificationResult = new DkimVerificationResult();
        $verificationResult->verified = true;
        $verificationResult->checkedAt = new \DateTimeImmutable();
        $verificationResult->errorMessage = null;

        $this->dkimVerificationService
            ->expects($this->once())
            ->method("verify")
            ->willReturn($verificationResult);

        $response = $this->consoleApi(
            $project,
            "POST",
            "/domains/verify",
            data: [
                'id' => $domain->getId(),
            ],
            scopes: [Scope::DOMAINS_WRITE]
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseData = $this->getJson();
        $this->assertTrue($responseData["dkim_verified"]);
        $this->assertNull($responseData["dkim_error_message"]);

        // Verify the domain was updated in the database
        $this->assertTrue($domain->getDkimVerified());
        $this->assertNull($domain->getDkimErrorMessage());
        $this->eventDispatcher->assertDispatched(DomainVerifiedEvent::class);
    }
}
