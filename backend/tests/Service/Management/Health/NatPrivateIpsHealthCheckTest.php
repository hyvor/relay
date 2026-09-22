<?php

namespace App\Tests\Service\Management\Health;

use App\Service\Ip\ServerIpResolver\PublicIpResolver;
use App\Service\Management\Health\NatPrivateIpsHealthCheck;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(NatPrivateIpsHealthCheck::class)]
class NatPrivateIpsHealthCheckTest extends KernelTestCase
{

    private PublicIpResolver&MockObject $publicIpResolver;
    private NatPrivateIpsHealthCheck $healthCheck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicIpResolver = $this->createMock(PublicIpResolver::class);
        $this->healthCheck = new NatPrivateIpsHealthCheck(
            $this->em,
            $this->publicIpResolver,
        );
    }

    public function test_returns_true_when_no_ips_have_a_private_ip(): void
    {
        IpAddressFactory::createOne([
            'ip_address' => '8.8.8.8',
        ]);

        $this->publicIpResolver->expects($this->never())->method('resolve');

        $result = $this->healthCheck->check();

        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function test_returns_true_when_all_private_ips_resolve_correctly(): void
    {
        IpAddressFactory::createOne([
            'ip_address' => '8.8.8.8',
            'private_ip_address' => '10.0.1.5',
        ]);

        $this->publicIpResolver->expects($this->once())
            ->method('resolve')
            ->with('10.0.1.5')
            ->willReturn('8.8.8.8');

        $result = $this->healthCheck->check();

        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function test_returns_false_when_resolved_public_ip_does_not_match(): void
    {
        IpAddressFactory::createOne([
            'ip_address' => '8.8.8.8',
            'private_ip_address' => '10.0.1.5',
        ]);

        $this->publicIpResolver->expects($this->once())
            ->method('resolve')
            ->with('10.0.1.5')
            ->willReturn('9.9.9.9');

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('invalid_nats', $data);
        $this->assertSame([
            [
                'private_ip' => '10.0.1.5',
                'expected_public_ip' => '8.8.8.8',
                'resolved_public_ip' => '9.9.9.9',
                'error' => null,
            ],
        ], $data['invalid_nats']);
    }

    public function test_returns_false_when_resolver_throws(): void
    {
        IpAddressFactory::createOne([
            'ip_address' => '8.8.8.8',
            'private_ip_address' => '10.0.1.5',
        ]);

        $this->publicIpResolver->expects($this->once())
            ->method('resolve')
            ->with('10.0.1.5')
            ->willThrowException(new \RuntimeException('Failed to resolve public IP from all external services'));

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $invalidNats = $this->getInvalidNats($this->healthCheck->getData());
        $this->assertSame('10.0.1.5', $invalidNats[0]['private_ip']);
        $this->assertSame('8.8.8.8', $invalidNats[0]['expected_public_ip']);
        $this->assertNull($invalidNats[0]['resolved_public_ip']);
        $this->assertSame(
            'Failed to resolve public IP from all external services',
            $invalidNats[0]['error'],
        );
    }

    public function test_only_reports_the_ip_that_fails_when_mixed(): void
    {
        IpAddressFactory::createOne([
            'ip_address' => '8.8.8.8',
            'private_ip_address' => '10.0.1.5',
        ]);
        IpAddressFactory::createOne([
            'ip_address' => '9.9.9.9',
            'private_ip_address' => '10.0.1.6',
        ]);
        // no private IP, should be skipped entirely
        IpAddressFactory::createOne([
            'ip_address' => '1.1.1.1',
        ]);

        $this->publicIpResolver->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnMap([
                ['10.0.1.5', '8.8.8.8'],
                ['10.0.1.6', 'wrong-ip'],
            ]);

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $invalidNats = $this->getInvalidNats($this->healthCheck->getData());
        $this->assertCount(1, $invalidNats);
        $this->assertSame('10.0.1.6', $invalidNats[0]['private_ip']);
        $this->assertSame('9.9.9.9', $invalidNats[0]['expected_public_ip']);
        $this->assertSame('wrong-ip', $invalidNats[0]['resolved_public_ip']);
    }

    /**
     * @param array<mixed> $data
     * @return array<int, array{
     *     private_ip: string,
     *     expected_public_ip: string,
     *     resolved_public_ip: ?string,
     *     error: ?string
     * }>
     */
    private function getInvalidNats(array $data): array
    {
        $this->assertArrayHasKey('invalid_nats', $data);
        /**
         * @var array<int, array{
         *     private_ip: string,
         *     expected_public_ip: string,
         *     resolved_public_ip: ?string,
         *     error: ?string
         * }> $invalidNats
         */
        $invalidNats = $data['invalid_nats'];
        return $invalidNats;
    }

}
