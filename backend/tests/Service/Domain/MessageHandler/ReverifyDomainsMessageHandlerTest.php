<?php

namespace App\Tests\Service\Domain\MessageHandler;

use App\Entity\Type\DomainStatus;
use App\Service\App\MessageTransport;
use App\Service\Domain\DkimVerificationResult;
use App\Service\Domain\DkimVerificationService;
use App\Service\Domain\DomainStatusService;
use App\Service\Domain\Exception\DkimVerificationFailedException;
use App\Service\Domain\Message\ReverifyDomainsMessage;
use App\Service\Domain\MessageHandler\ReverifyDomainsMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(ReverifyDomainsMessage::class)]
#[CoversClass(ReverifyDomainsMessageHandler::class)]
#[CoversClass(DomainStatusService::class)]
class ReverifyDomainsMessageHandlerTest extends KernelTestCase
{

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_reverifies_when_all_passes(bool $verified): void
    {
        $dkimVerificationService = $this->createMock(DkimVerificationService::class);
        $dkimVerificationService
            ->expects($this->exactly(2))
            ->method('verify')
            ->willReturnCallback(function () use ($verified) {
                $result = new DkimVerificationResult();
                $result->verified = $verified;
                $result->checkedAt = new \DateTimeImmutable();
                $result->errorMessage = null;

                return $result;
            });
        $this->container->set(DkimVerificationService::class, $dkimVerificationService);

        // not checked
        $pending = DomainFactory::createOne(['status' => DomainStatus::PENDING]);
        $suspended = DomainFactory::createOne(['status' => DomainStatus::SUSPENDED]);

        // verified: active -> active
        // unverified: active -> warning
        $active = DomainFactory::createOne([
            'domain' => 'example1.com',
            'status' => DomainStatus::ACTIVE
        ]);

        // verified: warning -> active
        // unverified: warning -> pending
        $warning = DomainFactory::createOne([
            'domain' => 'example3.com',
            'status' => DomainStatus::WARNING
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ReverifyDomainsMessage([DomainStatus::ACTIVE, DomainStatus::WARNING]));
        $transport->throwExceptions()->process();

        $this->assertSame(DomainStatus::PENDING, $pending->getStatus());
        $this->assertSame(DomainStatus::SUSPENDED, $suspended->getStatus());

        if ($verified) {
            $this->assertSame(DomainStatus::ACTIVE, $active->getStatus());
            $this->assertSame(DomainStatus::ACTIVE, $warning->getStatus());
        } else {
            $this->assertSame(DomainStatus::WARNING, $active->getStatus());
            $this->assertSame(DomainStatus::PENDING, $warning->getStatus());
        }
    }

    public function test_when_batch_size_exceeded(): void
    {
        $dkimVerificationService = $this->createMock(DkimVerificationService::class);
        $dkimVerificationService
            ->expects($this->exactly(5))
            ->method('verify')
            ->willReturnCallback(function () {
                $result = new DkimVerificationResult();
                $result->verified = false;
                $result->checkedAt = new \DateTimeImmutable();
                $result->errorMessage = 'DKIM verification failed';

                return $result;
            });
        $this->container->set(DkimVerificationService::class, $dkimVerificationService);

        $domains = DomainFactory::createMany(5, [
            'status' => DomainStatus::ACTIVE,
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ReverifyDomainsMessage([DomainStatus::ACTIVE, DomainStatus::WARNING], batchSize: 2));
        $transport->throwExceptions()->process();

        foreach ($domains as $domain) {
            $this->assertSame(DomainStatus::WARNING, $domain->getStatus());
            $this->assertNotNull($domain->getDkimCheckedAt());
            $this->assertSame('DKIM verification failed', $domain->getDkimErrorMessage());
        }
    }

    public function test_when_errors_exceed_maximum(): void
    {
        $dkimVerificationService = $this->createMock(DkimVerificationService::class);
        $dkimVerificationService
            ->method('verify')
            ->willThrowException(new DkimVerificationFailedException('Cloudflare missing'));
        $this->container->set(DkimVerificationService::class, $dkimVerificationService);

        DomainFactory::createMany(10, ['status' => DomainStatus::ACTIVE]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ReverifyDomainsMessage([DomainStatus::ACTIVE, DomainStatus::WARNING], batchSize: 2));
        $transport->throwExceptions()->process();

        $testLogger = $this->getTestLogger();
        $this->assertTrue(
            $testLogger->hasErrorThatContains('Too many errors while reverifying domains, stopping the process')
        );
    }

    public function test_with_pending(): void
    {
        $dkimVerificationService = $this->createMock(DkimVerificationService::class);
        $dkimVerificationService
            ->expects($this->exactly(2))
            ->method('verify')
            ->willReturnCallback(function () {
                $result = new DkimVerificationResult();
                $result->verified = true;
                $result->checkedAt = new \DateTimeImmutable();
                $result->errorMessage = null;

                return $result;
            });
        $this->container->set(DkimVerificationService::class, $dkimVerificationService);

        $domain1 = DomainFactory::createOne([
            'status' => DomainStatus::PENDING,
            'domain' => 'example1.com',
        ]);
        $domain2 = DomainFactory::createOne([
            'status' => DomainStatus::PENDING,
            'domain' => 'example2.com',
        ]);
        $domain3 = DomainFactory::createOne([
            'status' => DomainStatus::WARNING,
            'domain' => 'example3.com',
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ReverifyDomainsMessage([DomainStatus::PENDING], batchSize: 2));
        $transport->throwExceptions()->process();

        $this->assertSame(DomainStatus::ACTIVE, $domain1->getStatus());
        $this->assertSame(DomainStatus::ACTIVE, $domain2->getStatus());
        $this->assertSame(DomainStatus::WARNING, $domain3->getStatus());
    }

}