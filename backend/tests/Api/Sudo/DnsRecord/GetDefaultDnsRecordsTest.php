<?php

namespace App\Tests\Api\Sudo\DnsRecord;

use App\Api\Sudo\Controller\DnsRecordController;
use App\Api\Sudo\Object\DefaultDnsRecordObject;
use App\Service\Management\GoState\GoStateDnsRecord;
use App\Service\Management\GoState\GoStateDnsRecordsService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ServerFactory;
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
            'dkim_public_key' => 'testkey',
            'uuid' => '846fa554-e6c6-47a9-850d-8b7a5d4866c7'
        ]);

        $server1 = ServerFactory::createOne();

        $ip1 = IpAddressFactory::createOne(['server' => $server1, 'ip_address' => '1.1.1.1']); // on server 1
        $ip2 = IpAddressFactory::createOne(['server' => $server1, 'ip_address' => '2.2.2.2']); // on server 1
        $ip3 = IpAddressFactory::createOne(['ip_address' => '3.3.3.3']); // on server 2

        $this->sudoApi("GET", "/dns-records/default");

        /** @var array<array{type: string, host: string, content: string}> $json */
        $json = $this->getJson();

        $this->assertCount(9, $json);

        $records = [
            [
                'type' => 'A',
                'host' => 'smtp' . $ip1->getId() . '.mail.hyvor-relay.com',
                'content' => '1.1.1.1'
            ],
            [
                'type' => 'A',
                'host' => 'smtp' . $ip2->getId() . '.mail.hyvor-relay.com',
                'content' => '2.2.2.2',
            ],
            [
                'type' => 'A',
                'host' => 'smtp' . $ip3->getId() . '.mail.hyvor-relay.com',
                'content' => '3.3.3.3',
            ],
            // MX record
            [
                'type' => 'MX',
                'host' => 'mail.hyvor-relay.com',
                'content' => 'mx.mail.hyvor-relay.com',
            ],
            // A records for MX
            [
                'type' => 'A',
                'host' => 'mx.mail.hyvor-relay.com',
                'content' => '1.1.1.1',
            ],
            [
                'type' => 'A',
                'host' => 'mx.mail.hyvor-relay.com',
                'content' => '3.3.3.3',
            ],
            // SPF
            [
                'type' => 'TXT',
                'host' => 'mail.hyvor-relay.com',
                'content' => 'v=spf1 ip4:1.1.1.1 ip4:2.2.2.2 ip4:3.3.3.3 ~all',
            ],
            // DKIM
            [
                'type' => 'TXT',
                'host' => 'default._domainkey.mail.hyvor-relay.com',
                'content' => 'v=DKIM1; k=rsa; p=testkey'
            ],
            // HASH
            [
                'type' => 'TXT',
                'host' => '_hash.mail.hyvor-relay.com',
                'content' => 'd34e759a31b9ebe11ebb46ab4d23f4247052ab098f8f48dceef5293d0e4665f3',
            ]
        ];

        foreach ($records as $index => $record) {
            $this->assertEquals($record['type'], $json[$index]['type']);
            $this->assertEquals($record['host'], $json[$index]['host']);
            $this->assertEquals($record['content'], $json[$index]['content']);
        }
    }

}