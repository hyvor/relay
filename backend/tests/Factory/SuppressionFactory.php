<?php

namespace App\Tests\Factory;

use App\Entity\Suppression;
use App\Entity\Type\SuppressionReason;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Suppression>
 */
final class SuppressionFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Suppression::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => new \DateTimeImmutable(),
            'updated_at' => new \DateTimeImmutable(),
            'email' => self::faker()->email(),
            'reason' => self::faker()->randomElement([SuppressionReason::BOUNCE, SuppressionReason::COMPLAINT]),
            'description' => self::faker()->text(255),
        ];
    }

}
