<?php

namespace App\Service\Tls;

use App\Service\Instance\InstanceService;
use App\Service\Management\Health\Event\DnsServerCorrectlyPointedEvent;
use App\Service\Tls\Exception\AnotherTlsGenerationRequestInProgressException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(DnsServerCorrectlyPointedEvent::class, 'onDnsServerCorrectlyPointed')]
class TlsListener
{

    public function __construct(
        private InstanceService $instanceService,
        private MailTlsGenerator $mailTlsGenerator,
    ) {
    }

    /**
     * After the DNS server is pointed to, start generating mail TLS certificate if not exists
     */
    public function onDnsServerCorrectlyPointed(): void
    {
        $instance = $this->instanceService->getInstance();

        if ($instance->getMailTlsCertificateId()) {
            return;
        }

        try {
            $this->mailTlsGenerator->dispatchToGenerate();
            // @codeCoverageIgnoreStart
        } catch (AnotherTlsGenerationRequestInProgressException) {
            return; // ignore
        }
        // @codeCoverageIgnoreEnd
    }

}