<?php

namespace App\Service\Tls\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Lock\Key;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class GenerateCertificateMessage
{

    public function __construct(
        private int $tlsCertificateId,
        private Key $lockKey,
    ) {
    }

    public function getTlsCertificateId(): int
    {
        return $this->tlsCertificateId;
    }

    public function getLockKey(): Key
    {
        return $this->lockKey;
    }

}