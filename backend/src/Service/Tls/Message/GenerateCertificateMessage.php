<?php

namespace App\Service\Tls\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Lock\Key;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class GenerateCertificateMessage
{

    public function __construct(
        private int $tlsCertificateId
    ) {}

    public function getTlsCertificateId(): int
    {
        return $this->tlsCertificateId;
    }
}
