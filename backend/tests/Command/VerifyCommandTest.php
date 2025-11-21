<?php

namespace App\Tests\Command;

use App\Command\VerifyCommand;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(VerifyCommand::class)]
class VerifyCommandTest extends KernelTestCase
{

    public function test_verify(): void
    {
        $this->container->set(HttpClientInterface::class, new MockHttpClient());

        $command = $this->commandTester('verify');
        $exitCode = $command->execute([]);
        $this->assertSame(0, $exitCode);

        $output = $command->getDisplay();
        $this->assertStringContainsString('FAILED: Response body is empty.', $output); // OIDC
    }

}