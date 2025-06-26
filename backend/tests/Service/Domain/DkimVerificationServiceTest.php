<?php

namespace App\Tests\Service\Domain;

use App\Entity\Domain;
use App\Service\Domain\DkimVerificationService;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DkimVerificationService::class)]
class DkimVerificationServiceTest extends KernelTestCase
{

    private function doTest(
        Domain $domain,
        callable $dnsGetRecord,
        bool $verified,
        ?string $errorMessage = null
    ) : void
    {

        $service = new DkimVerificationService($dnsGetRecord);
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
            fn() => false,
            false,
            'DNS query failed'
        );

        $this->doTest(
            $exampleDomain,
            fn() => [],
            false,
            'No TXT records found for DKIM host'
        );

        $this->doTest(
            $exampleDomain,
            fn() => [['txt' => 'v=DKIM1; k=rsa; p=test_public_key']],
            true
        );

        $this->doTest(
            $exampleDomain,
            fn() => [['txt' => 'v=DKIM1; k=rsa; p=wrong_public_key']],
            false,
            'DKIM public key does not match'
        );

        $this->doTest(
            $exampleDomain,
            fn() => [[]],
            false,
            'No valid DKIM record found'
        );

    }

}