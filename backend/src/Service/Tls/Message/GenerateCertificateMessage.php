<?php

namespace App\Service\Tls\Message;

readonly class GenerateCertificateMessage
{

    public function __construct(
        private int $tlsCertificateId,
    ) {
    }

    public function getTlsCertificateId(): int
    {
        return $this->tlsCertificateId;
    }

}