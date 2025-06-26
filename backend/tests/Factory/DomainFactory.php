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
            'project' => ProjectFactory::createOne(),
            'domain' => self::faker()->domainName(),
            'dkim_selector' => 'rly' . self::faker()->word(),
            'dkim_public_key' => self::faker()->text(500),
            'dkim_private_key_encrypted' => self::faker()->text(500),
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

}