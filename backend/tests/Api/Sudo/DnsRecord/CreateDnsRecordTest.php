<?php

declare(strict_types=1);

namespace App\Tests\Api\Sudo\DnsRecord;

use App\Api\Sudo\Controller\DnsRecordController;
use App\Service\Dns\DnsRecordService;
use App\Service\Dns\Dto\CreateDnsRecordDto;
use App\Service\Dns\Event\CustomDnsRecordsChangedEvent;
use App\Tests\Case\WebTestCase;
use Hyvor\Internal\Bundle\Testing\TestEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DnsRecordController::class)]
#[CoversClass(CreateDnsRecordDto::class)]
#[CoversClass(DnsRecordService::class)]
class CreateDnsRecordTest extends WebTestCase
{
    public function test_creates_dns_record(): void
    {
        $this->sudoApi("POST", "/dns-records", [
            'type' => 'A',
            'subdomain' => 'www',
            'content' => '1.1.1.1',
            'ttl' => 600,
            'priority' => 0,
        ]);

        $this->assertResponseStatusCodeSame(201);
        $json = $this->getJson();
        $this->assertSame('A', $json['type']);
        $this->assertSame('www', $json['subdomain']);
        $this->assertSame('1.1.1.1', $json['content']);
        $this->assertSame(600, $json['ttl']);

        $this->getEd()->assertDispatched(CustomDnsRecordsChangedEvent::class);
    }

}
