<?php

namespace App\Service\Tls\Message;

use Symfony\Component\Lock\Key;

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