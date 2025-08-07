<?php

namespace App\Tests\Factory;

use App\Api\Console\Authorization\Scope;
use App\Entity\ApiKey;
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
            'key_hashed' => hash('sha256', self::faker()->uuid()),
            'scopes' => [
                ...array_map(
                    fn(Scope $scope) => $scope->value,
                    Scope::cases()
                )
            ],
            'is_enabled' => true,
            'last_accessed_at' => null,
        ];
    }

}
