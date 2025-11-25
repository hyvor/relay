<?php

namespace App\Service\Tls\Acme\Dto;

readonly class AccountInternalDto
{

    public function __construct(
        public string $privateKeyPem,
        public ?string $kid,
    ) {
    }

    public function getPrivateKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_private($this->privateKeyPem);
        assert($key !== false, 'Invalid private key PEM');
        return $key;
    }

}