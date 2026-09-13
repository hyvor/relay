<?php

namespace App\Tests\Factory;

use App\Entity\Kyc;
use App\Entity\Type\KycBusinessType;
use App\Entity\Type\KycStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Kyc>
 */
final class KycFactory extends PersistentObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Kyc::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => new \DateTimeImmutable(),
            'updated_at' => new \DateTimeImmutable(),
            'organization_id' => self::faker()->unique()->numberBetween(1, 1000000),
            'full_name' => self::faker()->name(),
            'business_type' => KycBusinessType::COMPANY,
            'business_name' => self::faker()->company(),
            'country' => 'US',
            'address' => self::faker()->address(),
            'phone' => '+1234567890',
            'website' => self::faker()->url(),
            'status' => KycStatus::PENDING,
            'submitted_at' => new \DateTimeImmutable(),
        ];
    }

}
