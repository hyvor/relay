<?php

namespace App\Tests\Api\Sudo\Tls;

use App\Api\Sudo\Controller\TlsController;
use App\Entity\TlsCertificate;
use App\Entity\Type\TlsCertificateStatus;
use App\Service\App\MessageTransport;
use App\Service\MxServer\MxServer;
use App\Service\Tls\MailTlsGenerator;
use App\Service\Tls\Message\GenerateCertificateMessage;
use App\Service\Tls\TlsCertificateService;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;

#[CoversClass(TlsController::class)]
#[CoversClass(MailTlsGenerator::class)]
#[CoversClass(TlsCertificateService::class)]
#[CoversClass(MxServer::class)]
class GenerateMailTlsCertificateTest extends WebTestCase
{

    public function test_creates_tls_certificate_and_pushes_job(): void
    {
        $this->sudoApi('POST', '/tls/mail-certs/generate');
        $this->assertResponseIsSuccessful();

        $tlsCerts = $this->em->getRepository(TlsCertificate::class)->findAll();
        $this->assertCount(1, $tlsCerts);
        $tlsCert = $tlsCerts[0];

        $this->assertSame($tlsCert->getDomain(), "mx.mail.hyvor-relay.com");
        $this->assertSame($tlsCert->getStatus(), TlsCertificateStatus::PENDING);

        $this->transport(MessageTransport::ASYNC)->queue()->assertContains(GenerateCertificateMessage::class);

        $lockKey = new Key('mail_tls_certificate_generation_lock');
        $lock = $this->getService(LockFactory::class)->createLockFromKey($lockKey);
        $lock->release();
    }

    public function test_when_lock_already_acquired(): void
    {
        $lockKey = new Key('mail_tls_certificate_generation_lock');
        $lock = $this->getService(LockFactory::class)
            ->createLockFromKey($lockKey, ttl: 300, autoRelease: false);
        $lock->acquire();

        $this->sudoApi('POST', '/tls/mail-certs/generate');
        $this->assertResponseStatusCodeSame(400);

        $responseData = $this->getJson();
        $this->assertSame(
            'Another TLS certificate generation request is already in progress.',
            $responseData['message']
        );

        $lock->release();
    }

}