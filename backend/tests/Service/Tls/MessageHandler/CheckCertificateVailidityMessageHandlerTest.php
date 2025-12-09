<?php

namespace App\Tests\Service\Tls\MessageHandler;

use App\Service\App\MessageTransport;
use App\Service\Tls\MessageHandler\CheckCertificateVailidityMessageHandler;
use App\Service\Tls\Message\CheckCertificateVailidityMessage;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use App\Tests\Factory\TlsCertificateFactory;
use App\Tests\Factory\InstanceFactory;
use App\Service\Tls\TlsCertificateService;
use App\Service\Tls\MailTlsGenerator;
use App\Service\Tls\Message\GenerateCertificateMessage;


#[CoversClass(CheckCertificateVailidityMessageHandler::class)]
#[CoversClass(TlsCertificateService::class)]
#[CoversClass(CheckCertificateVailidityMessage::class)]
#[CoversClass(MailTlsGenerator::class)]
class CheckCertificateVailidityMessageHandlerTest extends KernelTestCase
{
    public function test_no_renewal_needed_when_certificate_is_valid(): void
    {
        $tlsCertificate = TlsCertificateFactory::createOne([
            'validTo' => new \DateTimeImmutable('+40 days'),
        ]);

        InstanceFactory::createOne([
            'mail_tls_certificate_id' => $tlsCertificate->getId(),
        ]);

        $message = new CheckCertificateVailidityMessage();

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send($message);
        $transport->processOrFail();

        $this->assertTrue(
            $this->getTestLogger()->hasInfoThatContains("Mail TLS certificate is valid, no renewal needed")
        );

        $this->assertCount(0, $transport->queue());
    }

    public function test_refresh_certificate_when_expired(): void
    {
        $tlsCertificate = TlsCertificateFactory::createOne([
            'validTo' => new \DateTimeImmutable('-1 year'),
        ]);

        InstanceFactory::createOne([
            'mail_tls_certificate_id' => $tlsCertificate->getId(),
        ]);

        $message = new CheckCertificateVailidityMessage();

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send($message);
        $transport->throwExceptions()->process(1);

       $transport->queue()->assertContains(GenerateCertificateMessage::class);
    }
}
