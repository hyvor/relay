<?php

namespace App\Tests\Api\Sudo\DnsRecord;

use App\Api\Sudo\Controller\DnsRecordController;
use App\Api\Sudo\Object\DefaultDnsRecordObject;
use App\Service\Management\GoState\GoStateDnsRecord;
use App\Service\Management\GoState\GoStateDnsRecordsService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\IpAddressFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DnsRecordController::class)]
#[CoversClass(DefaultDnsRecordObject::class)]
#[CoversClass(GoStateDnsRecordsService::class)]
#[CoversClass(GoStateDnsRecord::class)]
class GetDefaultDnsRecordsTest extends WebTestCase
{

    public function test_get_default_dns_records(): void
    {
        $instance = InstanceFactory::createOne([
            'domain' => 'hyvor-relay.net',
            'dkim_public_key' => 'testkey'
        ]);

        $ip1 = IpAddressFactory::createOne(['ip_address' => '1.1.1.1']); // on server 1
        $ip2 = IpAddressFactory::createOne(['ip_address' => '2.2.2.2']); // on server 2

        $this->sudoApi("GET", "/dns-records/default");

        /** @var array<array{type: string, host: string, content: string}> $json */
        $json = $this->getJson();

        $this->assertCount(7, $json);

        $records = [
            [
                'type' => 'A',
                'host' => 'smtp' . $ip1->getId() . '.hyvor-relay.net',
                'content' => '1.1.1.1'
            ],
            [
                'type' => 'A',
                'host' => 'smtp' . $ip2->getId() . '.hyvor-relay.net',
                'content' => '2.2.2.2',
            ],
            // MX record
            [
                'type' => 'MX',
                'host' => 'hyvor-relay.net',
                'content' => 'mx.hyvor-relay.net',
            ],
            // A records for MX
            [
                'type' => 'A',
                'host' => 'mx.hyvor-relay.net',
                'content' => '1.1.1.1',
            ],
            [
                'type' => 'A',
                'host' => 'mx.hyvor-relay.net',
                'content' => '2.2.2.2',
            ],
            // SPF
            [
                'type' => 'TXT',
                'host' => 'hyvor-relay.net',
                'content' => 'v=spf1 ip4:1.1.1.1 ip4:2.2.2.2 ~all',
            ],
            // DKIM
            [
                'type' => 'TXT',
                'host' => 'default._domainkey.hyvor-relay.net',
                'content' => 'v=DKIM1; k=rsa; p=testkey'
            ],
        ];

        foreach ($records as $index => $record) {
            $this->assertEquals($record['type'], $json[$index]['type']);
            $this->assertEquals($record['host'], $json[$index]['host']);
            $this->assertEquals($record['content'], $json[$index]['content']);
        }
    }

}