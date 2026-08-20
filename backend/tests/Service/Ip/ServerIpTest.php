<?php

namespace App\Tests\Service\Ip;

use App\Service\Ip\ServerIpResolver\ServerIpResolver;
use App\Service\Ip\ServerIpResolver\ResolvedIp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerIpResolver::class)]
class ServerIpTest extends TestCase
{

    public function test_get_public_ips(): void
    {
        $ipService = new ServerIpResolver();
        $addresses = $ipService->getServerIpData();
        // @phpstan-ignore-next-line
        $this->assertIsArray($addresses);
    }

    public function test_get_public_ips_mocked(): void
    {
        $ipService = new ServerIpResolver(
            netGetInterfacesFunction: [$this, 'getMockedNetGetInterfaces'],
        );
        $addresses = $ipService->getServerIpData();
        $this->assertSame(
            [
                '54.12.34.56',
                '8.8.8.8',
            ],
            array_map(fn(ResolvedIp $result) => $result->publicIp, $addresses),
        );
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

        $interfaces = [];

        foreach ($addresses as $address) {
            $interfaces[] = [
                'up' => true,
                'unicast' => [
                    ['address' => $address],
                ],
            ];
        }

        // Add an interface that is down
        $interfaces[] = [
            'up' => false,
        ];

        return $interfaces;
    }

}
