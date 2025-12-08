<?php

namespace App\Tests\Service\Tls\MessageHandler;

use App\Service\App\MessageTransport;
use App\Service\Tls\MessageHandler\CheckCertificateVailidityMessageHandler;
use App\Service\Tls\Message\CheckCertificateVailidityMessage;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use App\Tests\Factory\TlsCertificateFactory;


#[CoversClass(CheckCertificateVailidityMessageHandler::class)]
#[CoversClass(TlsCertificateService::class)]
#[CoversClass(CheckCertificateVailidityMessage::class)]
#[CoversClass(MailTlsGenerator::class)]
class CheckCertificateVailidityMessageHandlerTest extends KernelTestCase
{
    public function test_refresh_certificate_when_expired(): void
    {
        $tlsCertificate = TlsCertificateFactory::createOne([
            'validTo' => new \DateTimeImmutable('-1 year'),
        ]);

        $message = new CheckCertificateVailidityMessage();

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send($message);
        $transport->processOrFail();

        $this->assertTrue(
            $this->getTestLogger()->hasInfoThatContains("Mail TLS certificate expires within threshold, starting renewal")
        );
    }
}