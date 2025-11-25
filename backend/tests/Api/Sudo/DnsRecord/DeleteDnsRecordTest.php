<?php

namespace App\Tests\Api\Sudo\DnsRecord;

use App\Api\Sudo\Controller\DnsRecordController;
use App\Entity\DnsRecord;
use App\Service\Dns\DnsRecordService;
use App\Service\Dns\Event\CustomDnsRecordsChangedEvent;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DnsRecordFactory;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DnsRecordController::class)]
#[CoversClass(DnsRecordService::class)]
class DeleteDnsRecordTest extends WebTestCase
{

    public function test_when_dns_record_not_found_returns_404(): void
    {
        $this->sudoApi("DELETE", "/dns-records/999999");

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_delete_dns_record(): void
    {
        $eventDispatcher = TestEventDispatcher::enable($this->container);
        $dnsRecord = DnsRecordFactory::createOne();
        $id = $dnsRecord->getId();

        $this->sudoApi("DELETE", "/dns-records/{$dnsRecord->getId()}");

        $this->assertResponseStatusCodeSame(204);

        $this->assertNull(
            $this->em->getRepository(DnsRecord::class)->find($id)
        );

        $eventDispatcher->assertDispatched(CustomDnsRecordsChangedEvent::class);
    }

}