<?php

namespace App\Tests\Api\Sudo\DnsRecord;

use App\Api\Sudo\Controller\DnsRecordController;
use App\Api\Sudo\Object\DnsRecordObject;
use App\Service\Dns\DnsRecordService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DnsRecordFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DnsRecordController::class)]
#[CoversClass(DnsRecordService::class)]
#[CoversClass(DnsRecordObject::class)]
class GetDnsRecordsTest extends WebTestCase
{

    public function test_gets_dns_records(): void
    {
        $record1 = DnsRecordFactory::createOne();
        $record2 = DnsRecordFactory::createOne();

        $this->sudoApi("GET", "/dns-records");

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertCount(2, $json);
    }

}