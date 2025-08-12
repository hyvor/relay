<?php

namespace App\Tests\Service\Management\Health;

use App\Service\Blacklist\DnsblQuery;
use App\Service\Blacklist\DnsblQueryResult;
use App\Service\Blacklist\Exception\DnsblLookupException;
use App\Service\Blacklist\IpBlacklist;
use App\Service\Blacklist\IpBlacklists;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Dns\Resolve\ResolveAnswer;
use App\Service\Dns\Resolve\ResolveResult;
use App\Service\Management\Health\NoneOfTheIpsAreOnKnownBlacklistsHealthCheck;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

// this also covers the Dnsbl classes
// we might need to move them to a separate test file in the future

#[CoversClass(NoneOfTheIpsAreOnKnownBlacklistsHealthCheck::class)]
#[CoversClass(DnsblQuery::class)]
#[CoversClass(IpBlacklists::class)]
#[CoversClass(IpBlacklist::class)]
#[CoversClass(DnsblQueryResult::class)]
#[CoversClass(DnsblLookupException::class)]
class NoneOfTheIpsAreOnKnownBlacklistsHealthCheckTest extends KernelTestCase
{

    /**
     * @return array{0: bool, 1: array<mixed>}
     */
    private function runTest(): array
    {
        $healthCheck = $this->container->get(NoneOfTheIpsAreOnKnownBlacklistsHealthCheck::class);
        assert($healthCheck instanceof NoneOfTheIpsAreOnKnownBlacklistsHealthCheck);

        $result = $healthCheck->check();

        return [$result, $healthCheck->getData()];
    }

    public function test_when_all_pass(): void
    {
        $queue = QueueFactory::createOne();
        $ip1 = IpAddressFactory::createOne(['ip_address' => '10.0.0.0', 'queue' => $queue]);
        $ip2 = IpAddressFactory::createOne(['ip_address' => '10.0.0.1', 'queue' => $queue]);
        $ip3 = IpAddressFactory::createOne(['ip_address' => '10.0.0.2', 'queue' => $queue]);
        $ip4 = IpAddressFactory::createOne(['ip_address' => '10.0.0.3', 'queue' => null]); // ignored


        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $dnsResolver->method('resolve')
            ->willReturnCallback(function (string $query, DnsType $type) {
                return new ResolveResult(3, []);
            });
        $this->container->set(DnsResolveInterface::class, $dnsResolver);

        [$result, $data] = $this->runTest();

        $this->assertTrue($result);

        $blacklists = [
            'barracuda',
            'spamcop',
            'mailspike',
            'psbl',
            '0spam'
        ];

        $this->assertIsInt($data['duration_ms']);

        $lists = $data['lists'];
        $this->assertIsArray($lists);

        foreach ($blacklists as $blacklist) {
            $this->assertIsArray($lists[$blacklist]);
            $this->assertArrayHasKey($ip1->getIpAddress(), $lists[$blacklist]);
            $this->assertArrayHasKey($ip2->getIpAddress(), $lists[$blacklist]);
            $this->assertArrayHasKey($ip3->getIpAddress(), $lists[$blacklist]);

            foreach ([$ip1, $ip2, $ip3] as $ip) {
                $this->assertIsArray($lists[$blacklist][$ip->getIpAddress()]);
                $this->assertEquals('ok', $lists[$blacklist][$ip->getIpAddress()]['status']);
                $this->assertIsInt($lists[$blacklist][$ip->getIpAddress()]['duration_ms']);
                $this->assertArrayNotHasKey('resolved_ip', $lists[$blacklist][$ip->getIpAddress()]);
                $this->assertArrayNotHasKey('error', $lists[$blacklist][$ip->getIpAddress()]);
            }

            $this->assertArrayNotHasKey($ip4->getIpAddress(), $lists[$blacklist]);
        }
    }

    public function test_when_one_blocked(): void
    {
        $queue = QueueFactory::createOne();
        $ip1 = IpAddressFactory::createOne(['ip_address' => '10.0.0.0', 'queue' => $queue]);
        $ip2 = IpAddressFactory::createOne(['ip_address' => '10.0.0.1', 'queue' => $queue]);

        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $dnsResolver->method('resolve')
            ->willReturnCallback(function (string $query, DnsType $type) {
                if ($query === '0.0.0.10.b.barracudacentral.org') {
                    return new ResolveResult(0, [
                        new ResolveAnswer('', '127.0.0.1')
                    ]);
                }
                return new ResolveResult(3, []);
            });
        $this->container->set(DnsResolveInterface::class, $dnsResolver);

        [$result, $data] = $this->runTest();

        $this->assertFalse($result);

        $lists = $data['lists'];
        $this->assertIsArray($lists);
        $baracudaList = $lists['barracuda'] ?? [];
        $this->assertIsArray($baracudaList);

        $ip1 = $baracudaList['10.0.0.0'];
        $this->assertIsArray($ip1);
        $this->assertEquals('blocked', $ip1['status']);
        $this->assertEquals('127.0.0.1', $ip1['resolved_ip']);

        $ip2 = $baracudaList['10.0.0.1'];
        $this->assertIsArray($ip2);
        $this->assertEquals('ok', $ip2['status']);
        $this->assertArrayNotHasKey('resolved_ip', $ip2);
    }

    public function test_on_errors(): void
    {
        $queue = QueueFactory::createOne();
        $ip1 = IpAddressFactory::createOne(['ip_address' => '10.0.0.0', 'queue' => $queue]);
        $ip2 = IpAddressFactory::createOne(['ip_address' => '10.0.0.1', 'queue' => $queue]);

        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $dnsResolver->method('resolve')
            ->willReturnCallback(function (string $query, DnsType $type) {
                if ($query === '0.0.0.10.b.barracudacentral.org') {
                    return new ResolveResult(1, []);
                }
                if ($query === '1.0.0.10.b.barracudacentral.org') {
                    throw new DnsResolvingFailedException('Simulated DNS resolving failure');
                }
                return new ResolveResult(3, []);
            });
        $this->container->set(DnsResolveInterface::class, $dnsResolver);

        [$result, $data] = $this->runTest();

        // result is still true, because errors are out of our hand
        // in the UI, we show them as warnings
        $this->assertTrue($result);

        $lists = $data['lists'];
        $this->assertIsArray($lists);
        $baracudaList = $lists['barracuda'] ?? [];
        $this->assertIsArray($baracudaList);

        $ip1 = $baracudaList['10.0.0.0'];
        $this->assertIsArray($ip1);
        $this->assertEquals('error', $ip1['status']);
        $this->assertEquals(
            'DNSBL lookup failed for Barracuda with query 0.0.0.10.b.barracudacentral.org (IP: 10.0.0.0): DNS lookup returned an error: Format error',
            $ip1['error']
        );

        $ip2 = $baracudaList['10.0.0.1'];
        $this->assertIsArray($ip2);
        $this->assertEquals('error', $ip2['status']);
        $this->assertEquals(
            'DNSBL lookup failed for Barracuda with query 1.0.0.10.b.barracudacentral.org (IP: 10.0.0.1): Simulated DNS resolving failure',
            $ip2['error']
        );
    }
}