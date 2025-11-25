<?php

namespace App\Tests\Factory;

use App\Entity\TlsCertificate;
use App\Entity\Type\TlsCertificateStatus;
use App\Entity\Type\TlsCertificateType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TlsCertificate>
 */
final class TlsCertificateFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return TlsCertificate::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => self::faker()->dateTime(),
            'updated_at' => self::faker()->dateTime(),
            'type' => TlsCertificateType::MAIL,
            'domain' => self::faker()->domainName(),
            'status' => TlsCertificateStatus::ACTIVE,
            'private_key_encrypted' => 'encrypted_string',
        ];
    }

}
