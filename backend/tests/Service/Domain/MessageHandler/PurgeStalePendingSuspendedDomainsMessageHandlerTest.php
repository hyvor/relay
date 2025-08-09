<?php

namespace App\Tests\Service\Domain\MessageHandler;

use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\Message\PurgeStalePendingSuspendedDomainsMessage;
use App\Service\Domain\MessageHandler\PurgeStalePendingSuspendedDomainsMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PurgeStalePendingSuspendedDomainsMessage::class)]
#[CoversClass(PurgeStalePendingSuspendedDomainsMessageHandler::class)]
class PurgeStalePendingSuspendedDomainsMessageHandlerTest extends KernelTestCase
{

    public function test_purges(): void
    {
        // deleted
        $domain1 = DomainFactory::createOne([
            'status' => DomainStatus::PENDING,
            'status_changed_at' => new \DateTimeImmutable('-30 days'),
        ]);

        // deleted
        $domain2 = DomainFactory::createOne([
            'status' => DomainStatus::PENDING,
            'status_changed_at' => new \DateTimeImmutable('-15 days'),
        ]);

        // not deleted
        $domain3 = DomainFactory::createOne([
            'status' => DomainStatus::PENDING,
            'status_changed_at' => new \DateTimeImmutable('-13 days'),
        ]);

        // not deleted
        $domain4 = DomainFactory::createOne([
            'status' => DomainStatus::ACTIVE,
            'status_changed_at' => new \DateTimeImmutable('-20 day'),
        ]);

        // deleted
        $domain5 = DomainFactory::createOne([
            'status' => DomainStatus::SUSPENDED,
            'status_changed_at' => new \DateTimeImmutable('-20 day'),
        ]);

        // not deleted
        $domain6 = DomainFactory::createOne([
            'status' => DomainStatus::WARNING,
            'status_changed_at' => new \DateTimeImmutable('-20 day'),
        ]);

        $transport = $this->transport('default');
        $transport->send(new PurgeStalePendingSuspendedDomainsMessage());
        $transport->throwExceptions()->process();

        $domains = $this->em->getRepository(Domain::class)->findAll();
        $this->assertCount(3, $domains);

        $domainsIds = array_map(fn(Domain $domain) => $domain->getId(), $domains);

        $this->assertNotContains($domain1->getId(), $domainsIds);
        $this->assertNotContains($domain2->getId(), $domainsIds);
        $this->assertContains($domain3->getId(), $domainsIds);
        $this->assertContains($domain4->getId(), $domainsIds);
        $this->assertNotContains($domain5->getId(), $domainsIds);
        $this->assertContains($domain6->getId(), $domainsIds);

        $testLogger = $this->getTestLogger();
        $this->assertTrue($testLogger->hasInfoThatPasses(function (LogRecord $log) {
            if ($log->message !== 'Purging stale pending and suspended domains') {
                return false;
            }

            $this->assertArrayHasKey('count', $log->context);
            $this->assertSame(3, $log->context['count']);
            return true;
        }));
    }

    public function test_when_0(): void
    {
        $transport = $this->transport('default');
        $transport->send(new PurgeStalePendingSuspendedDomainsMessage());
        $transport->throwExceptions()->process();

        $domains = $this->em->getRepository(Domain::class)->findAll();
        $this->assertCount(0, $domains);

        $testLogger = $this->getTestLogger();
        $this->assertTrue($testLogger->hasInfoThatContains('No stale pending or suspended domains to purge'));
    }

}