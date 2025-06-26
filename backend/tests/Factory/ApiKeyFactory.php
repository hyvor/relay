<?php

namespace App\Tests\Factory;

use App\Entity\ApiKey;
use App\Entity\Type\ApiKeyScope;
use App\Entity\Type\SendStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ApiKey>
 */
final class ApiKeyFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return ApiKey::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => new \DateTimeImmutable(),
            'updated_at' => new \DateTimeImmutable(),
            'name' => self::faker()->word(),
            'key' => self::faker()->word(),
            'scope' => self::faker()->randomElement(ApiKeyScope::class),
            'is_enabled' => true,
            'last_accessed_at' => new \DateTimeImmutable(),
        ];
    }

}
