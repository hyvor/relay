<?php

namespace App\Tests\Api\Sudo\DnsRecord;

use App\Api\Sudo\Controller\DnsRecordController;
use App\Entity\Type\DnsRecordType;
use App\Service\Dns\DnsRecordService;
use App\Service\Dns\Event\CustomDnsRecordsChangedEvent;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DnsRecordFactory;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DnsRecordController::class)]
#[CoversClass(DnsRecordService::class)]
class UpdateDnsRecordTest extends WebTestCase
{

    public function test_when_dns_record_not_found_returns_404(): void
    {
        $this->sudoApi("PATCH", "/dns-records/9999");
        $this->assertResponseStatusCodeSame(404);
    }

    public function test_update_dns_record_successful(): void
    {
        $dnsRecord = DnsRecordFactory::createOne([
            'type' => DnsRecordType::A,
            'subdomain' => 'old',
            'content' => 'old',
            'ttl' => 3600,
            'priority' => 0
        ]);

        $this->sudoApi("PATCH", "/dns-records/{$dnsRecord->getId()}", [
            'type' => 'CNAME',
            'subdomain' => 'updated',
            'content' => 'updates.example.com',
            'ttl' => 7200,
            'priority' => 10
        ]);

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertEquals('CNAME', $json['type']);
        $this->assertEquals('updated', $json['subdomain']);
        $this->assertEquals('updates.example.com', $json['content']);
        $this->assertEquals(7200, $json['ttl']);
        $this->assertEquals(10, $json['priority']);

        $record = $dnsRecord->_refresh();
        $this->assertEquals('CNAME', $record->getType()->value);
        $this->assertEquals('updated', $record->getSubdomain());
        $this->assertEquals('updates.example.com', $record->getContent());
        $this->assertEquals(7200, $record->getTtl());
        $this->assertEquals(10, $record->getPriority());

        $this->getEd()->assertDispatched(CustomDnsRecordsChangedEvent::class);
    }

}