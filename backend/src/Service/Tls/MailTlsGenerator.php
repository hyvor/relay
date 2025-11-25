<?php

namespace App\Service\Tls;

use App\Entity\TlsCertificate;
use App\Entity\Type\TlsCertificateType;
use App\Service\App\MessageTransport;
use App\Service\MxServer\MxServer;
use App\Service\Tls\Exception\AnotherTlsGenerationRequestInProgressException;
use App\Service\Tls\Message\GenerateCertificateMessage;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class MailTlsGenerator
{

    private const LOCK_NAME = 'mail_tls_certificate_generation_lock';

    public function __construct(
        private LockFactory $lockFactory,
        private MxServer $mxServer,
        private TlsCertificateService $tlsCertificateService,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @throws AnotherTlsGenerationRequestInProgressException
     */
    public function dispatchToGenerate(string $transport = MessageTransport::ASYNC): TlsCertificate
    {
        $key = new Key(self::LOCK_NAME);
        $lock = $this->lockFactory->createLockFromKey(
            $key,
            ttl: 300,  // 5 minutes
            autoRelease: false, // since the generation is handled asynchronously
        );

        if (!$lock->acquire()) {
            throw new AnotherTlsGenerationRequestInProgressException();
        }

        $domain = $this->mxServer->getMxHostname();
        $cert = $this->tlsCertificateService->createCertificate(
            TlsCertificateType::MAIL,
            $domain
        );

        $message = new GenerateCertificateMessage($cert->getId(), $key);
        $this->bus->dispatch($message, [
            new TransportNamesStamp($transport)
        ]);

        return $cert;
    }


}