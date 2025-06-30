<?php

namespace App\Tests\Factory;

use App\Entity\ApiIdempotencyRecord;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ApiIdempotencyRecord>
 */
final class ApiIdempotencyRecordFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return ApiIdempotencyRecord::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            "created_at" => new \DateTimeImmutable(),
            "updated_at" => new \DateTimeImmutable(),
            "project" => ProjectFactory::new(),
            "idempotency_key" => self::faker()->uuid(),
            "endpoint" => "/api/" . self::faker()->word(),
            "response" => [],
            "status_code" => self::faker()->randomElement([
                200,
                201,
                400,
                404,
                500,
            ]),
        ];
    }
}
