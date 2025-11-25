<?php

namespace App\Service\Tls;

class PrivateKey
{

    public static function generatePrivateKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        assert($key !== false);
        return $key;
    }

    public static function generatePrivateKeyPem(): string
    {
        return self::toPem(self::generatePrivateKey());
    }

    public static function toPem(\OpenSSLAsymmetricKey $privateKey): string
    {
        $pem = '';
        openssl_pkey_export($privateKey, $pem);
        assert(is_string($pem), 'Failed to export private key to PEM format');
        return $pem;
    }

}