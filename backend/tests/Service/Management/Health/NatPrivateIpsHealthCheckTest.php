<?php

namespace App\Tests\Service\Management\Health;

use App\Service\Management\Health\NatPrivateIpsHealthCheck;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[CoversClass(NatPrivateIpsHealthCheck::class)]
class NatPrivateIpsHealthCheckTest extends KernelTestCase
{

    private NatPrivateIpsHealthCheck $healthCheck;

    protected function setUp(): void
    {
        parent::setUp();

        Clock::set(new MockClock('2026-10-29 10:00:00'));
        $this->healthCheck = new NatPrivateIpsHealthCheck($this->em);
    }

    public function test_returns_true_when_no_ips_have_a_private_ip(): void
    {
        $server = ServerFactory::createOne();
        IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '8.8.8.8',
        ]);

        $this->assertTrue($this->healthCheck->check());
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function test_returns_true_when_all_servers_have_fresh_valid_results(): void
    {
        $server1 = ServerFactory::createOne([
            'nat_check' => ['checked_at' => '2026-10-29T09:45:00+00:00', 'invalid_nats' => []],
        ]);
        $server2 = ServerFactory::createOne([
            'nat_check' => ['checked_at' => '2026-10-29T09:01:00+00:00', 'invalid_nats' => []],
        ]);

        IpAddressFactory::createOne([
            'server' => $server1,
            'ip_address' => '8.8.8.8',
            'private_ip_address' => '10.0.1.5',
        ]);
        IpAddressFactory::createOne([
            'server' => $server2,
            'ip_address' => '9.9.9.9',
            'private_ip_address' => '10.0.1.5',
        ]);

        $this->assertTrue($this->healthCheck->check());
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function test_reports_invalid_nats_with_server_when_two_servers_use_same_private_ip(): void
    {
        $server1 = ServerFactory::createOne([
            'hostname' => 'server-1',
            'nat_check' => ['checked_at' => '2026-10-29T09:45:00+00:00', 'invalid_nats' => []],
        ]);
        $server2 = ServerFactory::createOne([
            'hostname' => 'server-2',
            'nat_check' => [
                'checked_at' => '2026-10-29T09:45:00+00:00',
                'invalid_nats' => [
                    [
                        'private_ip' => '10.0.1.5',
                        'expected_public_ip' => '9.9.9.9',
                        'resolved_public_ip' => '6.6.6.6',
                        'error' => null,
                    ],
                ],
            ],
        ]);

        IpAddressFactory::createOne([
            'server' => $server1,
            'ip_address' => '8.8.8.8',
            'private_ip_address' => '10.0.1.5',
        ]);
        IpAddressFactory::createOne([
            'server' => $server2,
            'ip_address' => '9.9.9.9',
            'private_ip_address' => '10.0.1.5',
        ]);

        $this->assertFalse($this->healthCheck->check());
        $this->assertSame([
            'invalid_nats' => [
                [
                    'server_hostname' => 'server-2',
                    'private_ip' => '10.0.1.5',
                    'expected_public_ip' => '9.9.9.9',
                    'resolved_public_ip' => '6.6.6.6',
                    'error' => null,
                ],
            ],
            'stale_servers' => [],
        ], $this->healthCheck->getData());
    }

    public function test_reports_missing_and_stale_results(): void
    {
        $stale = ServerFactory::createOne([
            'hostname' => 'stale',
            'nat_check' => ['checked_at' => '2026-10-29T08:59:00+00:00', 'invalid_nats' => []],
        ]);
        $missing = ServerFactory::createOne(['hostname' => 'missing']);

        IpAddressFactory::createOne([
            'server' => $stale,
            'ip_address' => '8.8.8.8',
            'private_ip_address' => '10.0.1.5',
        ]);
        IpAddressFactory::createOne([
            'server' => $missing,
            'ip_address' => '9.9.9.9',
            'private_ip_address' => '10.0.1.5',
        ]);

        $this->assertFalse($this->healthCheck->check());
        $this->assertSame([
            'invalid_nats' => [],
            'stale_servers' => ['stale', 'missing'],
        ], $this->healthCheck->getData());
    }

}
