<?php

namespace App\Service\Tls\Acme\Dto;

class FinalCertificate
{

    public function __construct(
        public string $certificatePem,
        public \DateTimeImmutable $validFrom,
        public \DateTimeImmutable $validTo,
    ) {
    }


    public static function fromPem(string $pem): self
    {
        $certData = openssl_x509_parse($pem);
        assert($certData !== false, 'Invalid PEM certificate data');

        $validFromTimestamp = $certData['validFrom_time_t'] ?? null;
        $validToTimestamp = $certData['validTo_time_t'] ?? null;

        if (!is_int($validFromTimestamp) || !is_int($validToTimestamp)) {
            throw new \InvalidArgumentException('Invalid timestamps in certificate data'); // @codeCoverageIgnore
        }

        $validFrom = new \DateTimeImmutable('@' . $validFromTimestamp);
        $validTo = new \DateTimeImmutable('@' . $validToTimestamp);

        return new self(
            certificatePem: $pem,
            validFrom: $validFrom,
            validTo: $validTo
        );
    }

}