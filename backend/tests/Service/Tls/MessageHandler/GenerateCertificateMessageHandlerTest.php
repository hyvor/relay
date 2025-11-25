<?php

namespace App\Tests\Service\Tls\MessageHandler;

use App\Entity\DnsRecord;
use App\Entity\Type\TlsCertificateStatus;
use App\Entity\Type\TlsCertificateType;
use App\Service\App\MessageTransport;
use App\Service\Tls\Acme\AcmeClient;
use App\Service\Tls\Acme\Dto\FinalCertificate;
use App\Service\Tls\Acme\Exception\AcmeException;
use App\Service\Tls\Acme\PendingOrder;
use App\Service\Tls\Message\GenerateCertificateMessage;
use App\Service\Tls\MessageHandler\GenerateCertificateMessageHandler;
use App\Service\Tls\PrivateKey;
use App\Service\Tls\TlsCertificateService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\TlsCertificateFactory;
use Hyvor\Internal\Util\Crypt\Encryption;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;

#[CoversClass(GenerateCertificateMessageHandler::class)]
#[CoversClass(TlsCertificateService::class)]
#[CoversClass(PrivateKey::class)]
#[CoversClass(GenerateCertificateMessage::class)]
class GenerateCertificateMessageHandlerTest extends KernelTestCase
{

    public function test_when_lock_is_acquired(): void
    {
        $logger = $this->getTestLogger();

        // first acquire lock
        $lockKey = new Key('mail_tls_certificate_generation_lock');
        $lock = $this->getService(LockFactory::class)->createLockFromKey($lockKey);
        $acquired = $lock->acquire();
        $this->assertTrue($acquired);

        // second key
        $lockKey2 = new Key('mail_tls_certificate_generation_lock');

        $message = new GenerateCertificateMessage(
            tlsCertificateId: 1,
            lockKey: $lockKey2
        );
        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send($message);
        $transport->processOrFail();

        $this->assertTrue(
            $logger->hasErrorThatContains("Could not acquire lock for TLS certificate generation, aborting")
        );
    }

    public function test_when_cert_not_found(): void
    {
        $lockKey = new Key('mail_tls_certificate_generation_lock');
        $message = new GenerateCertificateMessage(
            tlsCertificateId: 1,
            lockKey: $lockKey
        );
        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send($message);
        $transport->processOrFail();

        $this->assertTrue(
            $this->getTestLogger()->hasErrorThatContains("TLS Certificate not found, unable to continue")
        );
    }

    public function test_when_acme_client_fails(): void
    {
        $lockKey = new Key('mail_tls_certificate_generation_lock');
        $encryption = $this->getService(Encryption::class);
        $tlsCert = TlsCertificateFactory::createOne([
            'private_key_encrypted' => $encryption->encryptString(PrivateKey::generatePrivateKeyPem())
        ]);

        $acmeClient = $this->createMock(AcmeClient::class);
        $acmeClient->expects($this->once())->method('setLogger')
            ->willThrowException(new AcmeException('bad logger'));
        $this->container->set(AcmeClient::class, $acmeClient);

        $message = new GenerateCertificateMessage(
            tlsCertificateId: $tlsCert->getId(),
            lockKey: $lockKey
        );
        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send($message);
        $transport->processOrFail();
    }

    public function test_activates_certificate_mail_cert(): void
    {
        Clock::set(new MockClock());

        $instance = InstanceFactory::createOne();

        // previously acquired lock
        $lockKey = new Key('mail_tls_certificate_generation_lock');
        $lock = $this->getService(LockFactory::class)
            ->createLockFromKey($lockKey, ttl: 300, autoRelease: false);
        $acquired = $lock->acquire();

        $this->assertTrue($acquired);

        $encryption = $this->getService(Encryption::class);
        $privateKeyPem = PrivateKey::generatePrivateKeyPem();
        $tlsCert = TlsCertificateFactory::createOne([
            'type' => TlsCertificateType::MAIL,
            'private_key_encrypted' => $encryption->encryptString($privateKeyPem)
        ]);

        // mock acme client
        $acmeClient = $this->createMock(AcmeClient::class);
        $acmeClient->expects($this->once())->method('setLogger');
        $acmeClient->expects($this->once())->method('init');

        $pendingOrder = new PendingOrder(
            domain: 'mx.mail.hyvor-relay.com',
            dnsRecordValue: 'dummy-dns-value',
            orderUrl: 'https://acme.org/order/1',
            challengeUrl: 'https://acme.org/challenge/1',
            authorizationUrl: 'https://acme.org/authz/1',
            finalizeOrderUrl: 'https://acme.org/finalize/1',
        );
        $acmeClient->expects($this->once())->method('newOrder')->willReturn($pendingOrder);
        $acmeClient->expects($this->once())->method('finalizeOrder')
            ->willReturnCallback(
                function (PendingOrder $pendingOrderGot, \OpenSSLAsymmetricKey $privateKey) use (
                    $pendingOrder,
                    $privateKeyPem
                ) {
                    $this->assertSame($pendingOrderGot, $pendingOrder);

                    $this->assertSame(
                        $privateKeyPem,
                        PrivateKey::toPem($privateKey)
                    );

                    // get DNS record value
                    $dnsRecords = $this->em->getRepository(DnsRecord::class)->findAll();
                    $this->assertCount(1, $dnsRecords);
                    $dnsRecord = $dnsRecords[0];
                    $this->assertInstanceOf(DnsRecord::class, $dnsRecord);
                    $this->assertSame('_acme-challenge.mx', $dnsRecord->getSubdomain());
                    $this->assertSame('dummy-dns-value', $dnsRecord->getContent());

                    return new FinalCertificate(
                        certificatePem: 'This is PEM',
                        validFrom: new \DateTimeImmutable(),
                        validTo: new \DateTimeImmutable('+90 days'),
                    );
                }
            );
        $this->container->set(AcmeClient::class, $acmeClient);

        // dispatch generation
        $message = new GenerateCertificateMessage(
            tlsCertificateId: $tlsCert->getId(),
            lockKey: $lockKey
        );
        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send($message);
        $transport->processOrFail();

        $this->assertSame('This is PEM', $tlsCert->getCertificate());
        $this->assertSame(TlsCertificateStatus::ACTIVE, $tlsCert->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $tlsCert->getValidFrom());
        $this->assertInstanceOf(\DateTimeImmutable::class, $tlsCert->getValidTo());

        $this->assertSame(
            $tlsCert->getId(),
            $instance->getMailTlsCertificateId()
        );
    }

}