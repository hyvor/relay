<?php

namespace App\Tests\Service\Tls\Command;

use App\Service\App\MessageTransport;
use App\Service\Tls\Command\GenerateMailTlsCertificateCommand;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GenerateMailTlsCertificateCommand::class)]
class GenerateMailTlsCertificateCommandTest extends KernelTestCase
{

    public function test_execute(): void
    {
        $command = $this->commandTester('tls:generate-mail-certificate');
        $exitCode = $command->execute([]);

        $transport = $this->transport(MessageTransport::SYNC);
        $this->assertSame(1, $transport->getMessageCount());
        $this->assertSame(0, $exitCode);
    }

}