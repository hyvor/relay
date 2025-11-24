<?php

namespace App\Tests\Service\Management\Health;

use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\ResolveAnswer;
use App\Service\Dns\Resolve\ResolveResult;
use App\Service\Management\Health\DnsServerPointedHealthCheck;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InstanceFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(DnsServerPointedHealthCheck::class)]
class DnsServerPointedHealthCheckTest extends KernelTestCase
{

    public function test_when_dns_check_fail(): void
    {
        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $dnsResolver->method('resolve')
            ->willThrowException(new DnsResolvingFailedException('bad connection'));
        $this->container->set(DnsResolveInterface::class, $dnsResolver);

        $check = $this->getService(DnsServerPointedHealthCheck::class);
        $result = $check->check();

        $this->assertFalse($result);
        $data = $check->getData();
        $this->assertSame('DNS resolving failed: bad connection', $data['error']);
    }

    /**
     * @param ResolveAnswer[] $answers
     */
    #[TestWith([3, 'DNS query was not successful: Non-existent domain (NXDOMAIN)'])]
    #[TestWith([0, 'The required TXT record was not found.'])]
    #[TestWith([
        0,
        'The TXT record content does not match the instance UUID.',
        [new ResolveAnswer('name', 'wrongdata')]
    ])]
    public function test_when_fail(int $status, string $message, array $answers = []): void
    {
        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $dnsResolver->method('resolve')
            ->willReturn(new ResolveResult($status, $answers));
        $this->container->set(DnsResolveInterface::class, $dnsResolver);

        $check = $this->getService(DnsServerPointedHealthCheck::class);
        $result = $check->check();

        $this->assertFalse($result);
        $data = $check->getData();
        $this->assertIsString($data['error']);
        $this->assertStringContainsString($message, $data['error']);
    }

    public function test_dns_server_pointed_check(): void
    {
        $instance = InstanceFactory::createOne();

        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $dnsResolver->method('resolve')
            ->willReturn(
                new ResolveResult(0, [
                    new ResolveAnswer('name', $instance->getUuid())
                ])
            );
        $this->container->set(DnsResolveInterface::class, $dnsResolver);

        $check = $this->getService(DnsServerPointedHealthCheck::class);
        $result = $check->check();
        $this->assertTrue($result);
    }

}