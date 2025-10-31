<?php

namespace App\Tests\Factory;

use App\Entity\InfrastructureBounce;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<InfrastructureBounce>
 */
final class InfrastructureBounceFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return InfrastructureBounce::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'is_read' => false,
            'smtp_code' => self::faker()->numberBetween(400, 599),
            'smtp_enhanced_code' => '5.1.1',
            'smtp_message' => self::faker()->sentence(),
            'send_recipient_id' => self::faker()->numberBetween(1, 1000),
        ];
    }
}

