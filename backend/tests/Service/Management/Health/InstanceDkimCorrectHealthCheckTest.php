<?php

namespace App\Tests\Service\Management\Health;

use App\Entity\Domain;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\ResolveAnswer;
use App\Service\Dns\Resolve\ResolveResult;
use App\Service\Domain\Dkim;
use App\Service\Domain\DkimVerificationService;
use App\Service\Instance\InstanceService;
use App\Service\Management\Health\InstanceDkimCorrectHealthCheck;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InstanceDkimCorrectHealthCheck::class)]
class InstanceDkimCorrectHealthCheckTest extends KernelTestCase
{

    private function doTest(
        ResolveResult|true $result,
        bool $verified,
        ?string $errorMessage = null
    ): void {
        InstanceFactory::new()->withDefaultDkim()->create([
            'domain' => 'relay.net',
        ]);

        /** @var InstanceService $instanceService */
        $instanceService = $this->container->get(InstanceService::class);

        $resolver = $this->createMock(DnsResolveInterface::class);
        if ($result === true) {
            $resolver->method('resolve')->willThrowException(new DnsResolvingFailedException('bad request'));
        } else {
            $resolver->method('resolve')->willReturn($result);
        }

        $service = new InstanceDkimCorrectHealthCheck($instanceService, $resolver);
        $result = $service->check();

        $this->assertSame($verified, $result);

        if ($errorMessage !== null) {
            $data = $service->getData();
            $this->assertArrayHasKey('error', $data);
            $this->assertSame($errorMessage, $data['error']);
        } else {
            $this->assertArrayNotHasKey('error', $service->getData());
        }
    }

    public function test_verification(): void
    {
        $this->doTest(
            true,
            false,
            'DNS resolving failed for default._domainkey.relay.net: bad request'
        );

        $this->doTest(
            new ResolveResult(3, []),
            false,
            'DNS query for default._domainkey.relay.net failed with error: Non-existent domain (NXDOMAIN)'
        );

        $this->doTest(
            new ResolveResult(0, []),
            false,
            'No DKIM record found for default._domainkey.relay.net'
        );

        $this->doTest(
            new ResolveResult(0, [new ResolveAnswer('domain', 'v=DKIM1; k=rsa; p=test_public_key')]),
            false,
            'DKIM record does not match expected value'
        );

        $txt = Dkim::dkimTxtValue(DomainFactory::TEST_DKIM_PUBLIC_KEY);
        $this->doTest(
            new ResolveResult(0, [new ResolveAnswer('domain', $txt)]),
            true
        );
    }

}