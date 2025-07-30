<?php

namespace App\Tests\Factory;

use App\Entity\Instance;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Instance>
 */
final class InstanceFactory extends PersistentProxyObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Instance::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'domain' => self::faker()->domainName(),
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'dkim_public_key' => '',
            'dkim_private_key_encrypted' => '',
        ];
    }

    public function withDefaultDkim(): self
    {
        return $this->with([
            'dkim_public_key' => DomainFactory::TEST_DKIM_PUBLIC_KEY,
            'dkim_private_key_encrypted' =>  DomainFactory::TEST_DKIM_PRIVATE_KEY_ENCRYPTED,
        ]);
    }

}
