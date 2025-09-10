<?php

namespace App\Tests\Factory;

use App\Entity\Server;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Server>
 */
final class ServerFactory extends PersistentProxyObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Server::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'hostname' => self::faker()->unique()->domainName(),
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'last_ping_at' => self::faker()->optional(0.7)->passthrough(
                \DateTimeImmutable::createFromMutable(self::faker()->dateTime())
            ),
        ];
    }

}
