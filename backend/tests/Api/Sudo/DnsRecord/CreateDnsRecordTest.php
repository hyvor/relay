<?php

namespace App\Tests\Api\Sudo\DnsRecord;

use App\Api\Sudo\Controller\DnsRecordController;
use App\Service\Dns\DnsRecordService;
use App\Service\Dns\Dto\CreateDnsRecordDto;
use App\Tests\Case\WebTestCase;
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
        $this->assertEquals('A', $json['type']);
        $this->assertEquals('www', $json['subdomain']);
        $this->assertEquals('1.1.1.1', $json['content']);
        $this->assertEquals(600, $json['ttl']);
    }

}