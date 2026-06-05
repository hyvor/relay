<?php

namespace App\Tests\Service\ApiKey;

use App\Service\ApiKey\AllowedIp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowedIp::class)]
class AllowedIpTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validEntries(): iterable
    {
        yield 'single ipv4' => ['203.0.113.5'];
        yield 'ipv4 /24' => ['203.0.113.0/24'];
        yield 'ipv4 /32' => ['203.0.113.5/32'];
        yield 'single ipv6' => ['2001:db8::1'];
        yield 'ipv6 /48' => ['2001:db8::/48'];
        yield 'ipv6 /64' => ['2001:db8::/64'];
        yield 'ipv6 /128' => ['2001:db8::1/128'];
        yield 'private 10/8' => ['10.0.0.5'];
        yield 'private 10 cidr' => ['10.1.2.0/24'];
        yield 'private 172.16/12' => ['172.16.5.5'];
        yield 'private 192.168/16' => ['192.168.1.1'];
        yield 'cgnat' => ['100.64.0.1'];
        yield 'loopback' => ['127.0.0.1'];
        yield 'link-local v4' => ['169.254.0.1'];
        yield 'multicast v4' => ['224.0.0.1'];
        yield 'broadcast' => ['255.255.255.255'];
        yield 'ipv6 loopback' => ['::1'];
        yield 'ipv6 unique local' => ['fc00::1/64'];
        yield 'ipv6 link-local' => ['fe80::1/64'];
        yield 'ipv6 multicast' => ['ff02::1/128'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'empty' => [''];
        yield 'gibberish' => ['not-an-ip'];
        yield 'ipv4 too broad' => ['203.0.113.0/16'];
        yield 'ipv4 prefix too high' => ['203.0.113.5/33'];
        yield 'ipv6 too broad' => ['2001:db8::/32'];
        yield 'ipv6 prefix too high' => ['2001:db8::/129'];
        yield 'invalid prefix non-numeric' => ['203.0.113.5/abc'];
        yield 'empty prefix' => ['203.0.113.5/'];
    }

    #[DataProvider('validEntries')]
    public function test_validate_accepts(string $entry): void
    {
        $this->assertNull(AllowedIp::validateEntry($entry));
    }

    #[DataProvider('invalidEntries')]
    public function test_validate_rejects(string $entry): void
    {
        $this->assertNotNull(AllowedIp::validateEntry($entry));
    }

    public function test_normalize_ipv6(): void
    {
        $this->assertSame('2001:db8::/48', AllowedIp::normalizeEntry('2001:DB8::/48'));
        $this->assertSame('2001:db8::1', AllowedIp::normalizeEntry('2001:0db8:0000:0000:0000:0000:0000:0001'));
    }

    public function test_normalize_ipv4_passthrough(): void
    {
        $this->assertSame('203.0.113.5', AllowedIp::normalizeEntry('203.0.113.5'));
        $this->assertSame('203.0.113.0/24', AllowedIp::normalizeEntry('203.0.113.0/24'));
    }

    public function test_normalize_invalid_passthrough(): void
    {
        // unreachable in practice (callers validate first); guards the defensive path
        $this->assertSame('not-an-ip', AllowedIp::normalizeEntry('not-an-ip'));
    }

    public function test_matches(): void
    {
        $this->assertTrue(AllowedIp::matches('203.0.113.5', ['203.0.113.5']));
        $this->assertTrue(AllowedIp::matches('198.51.100.42', ['198.51.100.0/24']));
        $this->assertFalse(AllowedIp::matches('198.51.100.42', ['203.0.113.5', '203.0.113.0/24']));
        $this->assertFalse(AllowedIp::matches('203.0.113.5', []));
        $this->assertTrue(AllowedIp::matches('2001:db8::1234', ['2001:db8::/64']));
    }
}
