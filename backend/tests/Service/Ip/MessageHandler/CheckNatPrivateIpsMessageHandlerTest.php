<?php

namespace App\Tests\Service\Ip\MessageHandler;

use App\Service\Ip\Message\CheckNatPrivateIpsMessage;
use App\Service\Ip\MessageHandler\CheckNatPrivateIpsMessageHandler;
use App\Service\Ip\ServerIpResolver\PublicIpResolver;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use function Zenstruck\Foundry\Persistence\refresh;

#[CoversClass(CheckNatPrivateIpsMessage::class)]
#[CoversClass(CheckNatPrivateIpsMessageHandler::class)]
class CheckNatPrivateIpsMessageHandlerTest extends KernelTestCase
{

    private PublicIpResolver&MockObject $publicIpResolver;

    protected function setUp(): void
    {
        parent::setUp();

        Clock::set(new MockClock('2026-10-29 10:00:00'));

        $this->publicIpResolver = $this->createMock(PublicIpResolver::class);
        $this->container->set(PublicIpResolver::class, $this->publicIpResolver);
    }

    private function process(): void
    {
        $transport = $this->transport('scheduler_server');
        $transport->send(new CheckNatPrivateIpsMessage());
        $transport->throwExceptions()->process();
    }

    public function test_checks_only_private_ips_of_current_server(): void
    {
        $server = ServerFactory::createOne(['hostname' => 'hyvor-relay']);
        $otherServer = ServerFactory::createOne(['hostname' => 'other']);

        IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '8.8.8.8',
            'private_ip_address' => '10.0.1.5',
        ]);
        IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '9.9.9.9',
            'private_ip_address' => '10.0.1.6',
        ]);
        IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '7.7.7.7',
            'private_ip_address' => '10.0.1.7',
        ]);
        // no private IP, skipped
        IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '1.1.1.1',
        ]);
        // other server's IP, not checked
        IpAddressFactory::createOne([
            'server' => $otherServer,
            'ip_address' => '2.2.2.2',
            'private_ip_address' => '10.0.2.5',
        ]);

        $this->publicIpResolver->expects($this->exactly(3))
            ->method('resolve')
            ->willReturnCallback(fn(string $privateIp) => match ($privateIp) {
                '10.0.1.5' => '8.8.8.8',
                '10.0.1.6' => '6.6.6.6',
                default => throw new \RuntimeException('Failed to resolve'),
            });

        $this->process();

        refresh($server);
        $this->assertSame([
            'checked_at' => '2026-10-29T10:00:00+00:00',
            'invalid_nats' => [
                [
                    'private_ip' => '10.0.1.6',
                    'expected_public_ip' => '9.9.9.9',
                    'resolved_public_ip' => '6.6.6.6',
                    'error' => null,
                ],
                [
                    'private_ip' => '10.0.1.7',
                    'expected_public_ip' => '7.7.7.7',
                    'resolved_public_ip' => null,
                    'error' => 'Failed to resolve',
                ],
            ],
        ], $server->getNatCheck());

        refresh($otherServer);
        $this->assertNull($otherServer->getNatCheck());
    }

    public function test_clears_result_when_server_has_no_private_ips(): void
    {
        $server = ServerFactory::createOne([
            'hostname' => 'hyvor-relay',
            'nat_check' => ['checked_at' => '2026-10-29T09:00:00+00:00', 'invalid_nats' => []],
        ]);
        IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '8.8.8.8',
        ]);

        $this->publicIpResolver->expects($this->never())->method('resolve');

        $this->process();

        refresh($server);
        $this->assertNull($server->getNatCheck());
    }

    public function test_no_server_found(): void
    {
        $this->publicIpResolver->expects($this->never())->method('resolve');

        $this->process();

        $this->addToAssertionCount(1);
    }

}
