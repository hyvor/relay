<?php

namespace App\Service\Domain;

class Dkim
{

    public const string DKIM_SUBDOMAIN = '_domainkey';

    public static function generateDkimSelector(): string
    {
        return 'rly' . date('YmdHis') . bin2hex(random_bytes(4));
    }

    /**
     * @return array{public: string, private: string}
     */
    public static function generateDkimKeys(): array
    {
        $privateKey = \openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA
        ]);
        assert($privateKey !== false);

        openssl_pkey_export($privateKey, $privateKeyString);
        $details = openssl_pkey_get_details($privateKey);
        assert($details !== false);

        $publicKey = $details['key'];

        assert(is_string($publicKey));
        assert(is_string($privateKeyString));

        return [
            'public' => $publicKey,
            'private' => $privateKeyString
        ];
    }

    public static function dkimHost(string $selector, string $domain): string
    {
        return sprintf('%s.%s.%s', $selector, self::DKIM_SUBDOMAIN, $domain);
    }

    public static function dkimTxtValue(string $publicKey): string
    {
        return sprintf('v=DKIM1; k=rsa; p=%s', self::cleanKey($publicKey));
    }

    /**
     * Cleaned to be used in DKIM DNS records.
     */
    public static function cleanKey(string $key): string
    {
        return str_replace([
            '-----BEGIN PUBLIC KEY-----',
            '-----END PUBLIC KEY-----',
            '-----BEGIN PRIVATE KEY-----',
            '-----END PRIVATE KEY-----',
            "\n",
            "\r"
        ], '', $key);
    }

}
