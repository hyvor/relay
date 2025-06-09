<?php

namespace App\Tests\Factory;

use App\Entity\Domain;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Domain>
 */
final class DomainFactory extends PersistentProxyObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Domain::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'hyvor_user_id' => self::faker()->numberBetween(1, 10000),
            'domain' => self::faker()->domainName(),
            'dkim_public_key' => self::faker()->text(500),
            'dkim_private_key' => self::faker()->text(500),
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

}