<?php

namespace App\Tests\Service\Ip;

use App\Entity\IpAddress;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Dns\Resolve\ResolveAnswer;
use App\Service\Dns\Resolve\ResolveResult;
use App\Service\Instance\InstanceService;
use App\Service\Ip\Dto\PtrValidationDto;
use App\Service\Ip\Ptr;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InstanceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Ptr::class)]
#[CoversClass(PtrValidationDto::class)]
class PtrTest extends KernelTestCase
{

    public function test_get_ptr_domain(): void
    {
        $ipAddress = new IpAddress();
        $ipAddress->setId(25);
        $this->assertSame('smtp25.relay.hyvor.com', Ptr::getPtrDomain($ipAddress, 'relay.hyvor.com'));
    }

    public function test_validate_dns_error(): void
    {
        $instance = InstanceFactory::createOne([
            'domain' => 'hyvorrelay.com',
        ]);

        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $dnsResolver
            ->method('resolve')
            ->willReturnCallback(function (string $domain, DnsType $type) {
                if ($domain === 'smtp42.hyvorrelay.com') {
                    $this->assertSame(DnsType::A, $type);
                    throw new DnsResolvingFailedException('Simulated DNS failure');
                } elseif ($domain === '1.1.1.1.in-addr.arpa') {
                    $this->assertSame(DnsType::PTR, $type);
                    return new ResolveResult(3, []); // NXDOMAIN
                }
            });

        $instanceService = $this->getService(InstanceService::class);
        $ptr = new Ptr($instanceService, $dnsResolver);

        $ipAddress = new IpAddress();
        $ipAddress->setId(42);
        $ipAddress->setIpAddress('1.1.1.1');
        $result = $ptr->validate($ipAddress);

        $this->assertFalse($result['forward']->valid);
        $this->assertSame('DNS resolving failed: Simulated DNS failure', $result['forward']->error);

        $this->assertFalse($result['reverse']->valid);
        $this->assertSame('DNS error: Non-existent domain (NXDOMAIN)', $result['reverse']->error);
    }

    public function test_validate_partial_success(): void
    {
        $instance = InstanceFactory::createOne([
            'domain' => 'hyvorrelay.com',
        ]);

        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $dnsResolver
            ->method('resolve')
            ->willReturnCallback(function (string $domain, DnsType $type) {
                if ($domain === 'smtp43.hyvorrelay.com') {
                    $this->assertSame(DnsType::A, $type);
                    // forward has 2 records, should only have one
                    return new ResolveResult(0, [
                        new ResolveAnswer('smtp43.hyvorrelay.com', '1.1.1.1'),
                        new ResolveAnswer('smtp43.hyvorrelay.com', '2.2.2.2'),
                    ]);
                } elseif ($domain === '4.3.2.1.in-addr.arpa') {
                    $this->assertSame(DnsType::PTR, $type);
                    return new ResolveResult(0, [
                        new ResolveAnswer("4.3.2.1.in-addr.arpa", 'smtp43.hyvorrelay.com.'),
                    ]);
                }
            });

        $instanceService = $this->getService(InstanceService::class);
        $ptr = new Ptr($instanceService, $dnsResolver);

        $ipAddress = new IpAddress();
        $ipAddress->setId(43);
        $ipAddress->setIpAddress('1.2.3.4');
        $result = $ptr->validate($ipAddress);

        $this->assertFalse($result['forward']->valid);
        $this->assertSame('A record mismatch: expected "1.2.3.4", got "1.1.1.1, 2.2.2.2"', $result['forward']->error);

        $this->assertTrue($result['reverse']->valid);
        $this->assertNull($result['reverse']->error);
    }

}