<?php

namespace App\Tests\Service\Management\Health;

use App\Entity\Domain;
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
        callable $dnsGetRecord,
        bool $verified,
        ?string $errorMessage = null
    ) : void
    {
        InstanceFactory::new()->withDefaultDkim()->create([
            'domain' => 'relay.net',
        ]);

        /** @var InstanceService $instanceService */
        $instanceService = $this->container->get(InstanceService::class);

        $service = new InstanceDkimCorrectHealthCheck($instanceService, $dnsGetRecord);
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
            fn() => false,
            false,
            'DNS lookup failed for DKIM record for default._domainkey.relay.net'
        );

        $this->doTest(
            fn() => [],
            false,
            'No DKIM record found for default._domainkey.relay.net'
        );

        $this->doTest(
            fn() => [['txt' => 'v=DKIM1; k=rsa; p=test_public_key']],
            false,
            'DKIM record does not match expected value'
        );

        $txt = Dkim::dkimTxtValue(DomainFactory::TEST_DKIM_PUBLIC_KEY);
        $this->doTest(
            fn() => [['txt' => $txt]],
            true
        );

    }

}