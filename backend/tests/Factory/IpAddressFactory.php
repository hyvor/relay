<?php

namespace App\Tests\Factory;

use App\Entity\IpAddress;
use App\Entity\Server;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<IpAddress>
 */
final class IpAddressFactory extends PersistentProxyObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return IpAddress::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'server' => ServerFactory::new(),
            'ip_address' => self::faker()->ipv4(),
            'queue' => null,
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

}
