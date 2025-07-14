<?php

namespace App\Tests\Service\Ip;

use App\Service\Ip\CurrentServerIp;
use PHPUnit\Framework\TestCase;

class IpServiceTest extends TestCase
{

    public function test_get_public_ips(): void
    {
        $ipService = new CurrentServerIp();
        $addresses = $ipService->getPublicV4IpAddresses();
        // @phpstan-ignore-next-line
        $this->assertIsArray($addresses);
    }

    public function test_get_public_ips_mocked(): void
    {
        $ipService = new CurrentServerIp(
            netGetInterfacesFunction: [$this, 'getMockedNetGetInterfaces']
        );
        $addresses = $ipService->getPublicV4IpAddresses();
        $this->assertSame(
            [
                '54.12.34.56',
                '8.8.8.8',
            ],
            $addresses
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

            // ipV6
            '2401:fa00:0000:0000:0000:0000:abcd:5678',

            // public
            '8.8.8.8',
            '54.12.34.56'
        ];

        $interfaces = [];

        foreach ($addresses as $address) {
            $interfaces[] = [
                'up' => true,
                'unicast' => [
                    ['address' => $address]
                ]
            ];
        }

        // Add an interface that is down
        $interfaces[] = [
            'up' => false,
        ];

        return $interfaces;
    }

}