<?php

namespace App\Tests\Service\Ip;

use App\Service\Ip\ServerIpResolver\PublicIpResolver;
use App\Service\Ip\ServerIpResolver\ServerIpResolver;
use App\Service\Ip\ServerIpResolver\ResolvedIp;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ServerIpResolver::class)]
class ServerIpResolverTest extends KernelTestCase
{

    public function test_get_public_ips(): void
    {
        $ipService = $this->getService(ServerIpResolver::class);
        $addresses = $ipService->resolveIps();
        $this->assertGreaterThanOrEqual(0, count($addresses));
    }

    public function test_get_public_ips_mocked(): void
    {
        $ipService = $this->getService(ServerIpResolver::class);
        $ipService->mockNetGetInterfacesFunction([$this, 'getMockedNetGetInterfaces']);
        $addresses = $ipService->resolveIps();
        $this->assertSame(
            [
                '54.12.34.56',
                '8.8.8.8',
            ],
            array_map(fn(ResolvedIp $result) => $result->publicIp, $addresses),
        );
    }

    public function test_resolve_ips_behind_nat_using_nat_map(): void
    {
        $this->setConfig('natNetwork', '10.0.0.0/8');
        $this->setConfig('natMap', '10.0.1.5=8.8.4.4, 10.0.1.6=9.9.9.9');

        $publicIpResolverMock = $this->createMock(PublicIpResolver::class);
        $publicIpResolverMock->expects($this->never())->method('resolve');
        $this->container->set(PublicIpResolver::class, $publicIpResolverMock);

        $ipService = $this->getService(ServerIpResolver::class);
        $ipService->mockNetGetInterfacesFunction(fn() => $this->fakeInterfaces(['10.0.1.5', '10.0.1.6']));

        $addresses = $ipService->resolveIps();

        $this->assertCount(2, $addresses);
        $this->assertSame('8.8.4.4', $addresses[0]->publicIp);
        $this->assertSame('10.0.1.5', $addresses[0]->privateIp);
        $this->assertSame('9.9.9.9', $addresses[1]->publicIp);
        $this->assertSame('10.0.1.6', $addresses[1]->privateIp);
    }

    public function test_resolve_ips_behind_nat_falls_back_to_public_ip_resolver(): void
    {
        $this->setConfig('natNetwork', '10.0.0.0/8');
        $this->setConfig('natMap', '');

        $publicIpResolverMock = $this->createMock(PublicIpResolver::class);
        $publicIpResolverMock->expects($this->once())
            ->method('resolve')
            ->with('10.0.1.5')
            ->willReturn('1.1.1.1');
        $this->container->set(PublicIpResolver::class, $publicIpResolverMock);

        $ipService = $this->getService(ServerIpResolver::class);
        $ipService->mockNetGetInterfacesFunction(fn() => $this->fakeInterfaces(['10.0.1.5']));

        $addresses = $ipService->resolveIps();

        $this->assertCount(1, $addresses);
        $this->assertSame('1.1.1.1', $addresses[0]->publicIp);
        $this->assertSame('10.0.1.5', $addresses[0]->privateIp);
    }

    public function test_resolve_ips_behind_nat_ignores_invalid_nat_map_entries(): void
    {
        $this->setConfig('natNetwork', '10.0.0.0/8');
        // 10.0.1.5 => invalid public ip, ignored
        // 10.0.1.6 => valid entry, used as-is
        // "badentry" => malformed, no "=", ignored
        $this->setConfig('natMap', '10.0.1.5=999.999.999.999, 10.0.1.6=9.9.9.9, badentry');

        $publicIpResolverMock = $this->createMock(PublicIpResolver::class);
        $publicIpResolverMock->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnMap([
                ['10.0.1.5', '4.2.2.1'],
                ['10.0.1.7', '4.2.2.2'],
            ]);
        $this->container->set(PublicIpResolver::class, $publicIpResolverMock);

        $ipService = $this->getService(ServerIpResolver::class);
        $ipService->mockNetGetInterfacesFunction(
            fn() => $this->fakeInterfaces(['10.0.1.5', '10.0.1.6', '10.0.1.7']),
        );

        $addresses = $ipService->resolveIps();

        $this->assertCount(3, $addresses);
        $this->assertSame('4.2.2.1', $addresses[0]->publicIp);
        $this->assertSame('10.0.1.5', $addresses[0]->privateIp);
        $this->assertSame('9.9.9.9', $addresses[1]->publicIp);
        $this->assertSame('10.0.1.6', $addresses[1]->privateIp);
        $this->assertSame('4.2.2.2', $addresses[2]->publicIp);
        $this->assertSame('10.0.1.7', $addresses[2]->privateIp);
    }

    public function test_resolve_ips_behind_nat_returns_empty_when_no_private_ips_match(): void
    {
        $this->setConfig('natNetwork', '10.0.0.0/8');
        $this->setConfig('natMap', '');

        $publicIpResolverMock = $this->createMock(PublicIpResolver::class);
        $publicIpResolverMock->expects($this->never())->method('resolve');
        $this->container->set(PublicIpResolver::class, $publicIpResolverMock);

        $ipService = $this->getService(ServerIpResolver::class);
        $ipService->mockNetGetInterfacesFunction(fn() => $this->fakeInterfaces(['8.8.8.8', '54.12.34.56']));

        $addresses = $ipService->resolveIps();

        $this->assertSame([], $addresses);
    }

    /**
     * @param string[] $addresses
     * @return array<mixed>
     */
    private function fakeInterfaces(array $addresses): array
    {
        $interfaces = [];

        foreach ($addresses as $address) {
            $interfaces[] = [
                'up' => true,
                'unicast' => [
                    ['address' => $address],
                ],
            ];
        }

        return $interfaces;
    }

    /**
     * @return array<mixed>
     */
    public function getMockedNetGetInterfaces(): array
    {
        $addresses = [
            // private
            '127.0.0.1',
            '192.168.1.1',
            '172.20.5.4',
            '10.0.0.5',
            '100.78.45.84', // CGNAT

            // ipV6
            '2401:fa00:0000:0000:0000:0000:abcd:5678',

            // public
            '8.8.8.8',
            '54.12.34.56',
        ];

        $interfaces = $this->fakeInterfaces($addresses);

        // Add an interface that is down
        $interfaces[] = [
            'up' => false,
        ];

        return $interfaces;
    }

}
