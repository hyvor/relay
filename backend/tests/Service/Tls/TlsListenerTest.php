<?php

namespace App\Tests\Service\Tls;

use App\Service\Management\Health\Event\DnsServerCorrectlyPointedEvent;
use App\Service\Tls\MailTlsGenerator;
use App\Service\Tls\TlsListener;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\TlsCertificateFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TlsListener::class)]
class TlsListenerTest extends KernelTestCase
{

    public function test_calls_mail_tls_generator_on_dns_server_correctly_pointed_event(): void
    {
        $generator = $this->createMock(MailTlsGenerator::class);
        $generator->expects($this->once())->method('dispatchToGenerate');
        $this->container->set(MailTlsGenerator::class, $generator);

        $event = new DnsServerCorrectlyPointedEvent();
        $this->getEd()->dispatch($event);
    }

    public function test_mail_tls_ignored_when_already_there(): void
    {
        $tlsCert = TlsCertificateFactory::createOne();
        $instance = InstanceFactory::createOne(['mail_tls_certificate_id' => $tlsCert->getId()]);

        $generator = $this->createMock(MailTlsGenerator::class);
        $generator->expects($this->never())->method('dispatchToGenerate');
        $this->container->set(MailTlsGenerator::class, $generator);

        $event = new DnsServerCorrectlyPointedEvent();
        $this->getEd()->dispatch($event);
    }

}