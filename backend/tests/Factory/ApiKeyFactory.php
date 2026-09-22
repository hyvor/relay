<?php

namespace App\Tests\Factory;

use Hyvor\Internal\CloudApi\Scope\RelayScope;
use App\Entity\ApiKey;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ApiKey>
 */
final class ApiKeyFactory extends PersistentObjectFactory
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
                    fn(RelayScope $scope) => $scope->value,
                    RelayScope::cases()
                )
            ],
            'is_enabled' => true,
            'last_accessed_at' => null,
            'allowed_ips' => [],
        ];
    }

}
