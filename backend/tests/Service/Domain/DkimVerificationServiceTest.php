<?php

namespace App\Tests\Service\Domain;

use App\Entity\Domain;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\ResolveAnswer;
use App\Service\Dns\Resolve\ResolveResult;
use App\Service\Domain\DkimVerificationService;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DkimVerificationService::class)]
class DkimVerificationServiceTest extends KernelTestCase
{

    private function doTest(
        Domain $domain,
        ResolveResult|true $result,
        bool $verified,
        ?string $errorMessage = null
    ): void {
        $resolver = $this->createMock(DnsResolveInterface::class);

        if ($result === true) {
            $resolver->method('resolve')->willThrowException(new DnsResolvingFailedException('bad request'));
        } else {
            $resolver->method('resolve')->willReturn($result);
        }

        $service = new DkimVerificationService($resolver);
        $result = $service->verify($domain);

        $this->assertSame($verified, $result->verified);
        $this->assertSame($errorMessage, $result->errorMessage);
    }

    public function test_verification(): void
    {
        $exampleDomain = new Domain();
        $exampleDomain->setDomain('example.com');
        $exampleDomain->setDkimSelector('selector');
        $exampleDomain->setDkimPublicKey('test_public_key');

        $this->doTest(
            $exampleDomain,
            new ResolveResult(20, []),
            false,
            'DNS query failed with error: Unknown error code: 20'
        );

        $this->doTest(
            $exampleDomain,
            true,
            false,
            'DNS resolving failed: bad request'
        );

        $this->doTest(
            $exampleDomain,
            new ResolveResult(3, []),
            false,
            'DNS query failed with error: Non-existent domain (NXDOMAIN)'
        );

        $this->doTest(
            $exampleDomain,
            new ResolveResult(0, []),
            false,
            'No TXT records found for DKIM host'
        );

        $this->doTest(
            $exampleDomain,
            new ResolveResult(0, [new ResolveAnswer('example.com', 'v=DKIM1; k=rsa; p=test_public_key')]),
            true
        );

        $this->doTest(
            $exampleDomain,
            new ResolveResult(0, [new ResolveAnswer('example.com', 'v=DKIM1; k=rsa; p=wrong_public_key')]),
            false,
            'DKIM public key does not match'
        );

        $this->doTest(
            $exampleDomain,
            new ResolveResult(0, [new ResolveAnswer('example.com', 'test')]),
            false,
            'No valid DKIM record found'
        );
    }

}