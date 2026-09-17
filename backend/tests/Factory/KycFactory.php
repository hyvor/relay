<?php

namespace App\Tests\Factory;

use App\Entity\Kyc;
use App\Entity\Type\KycAccountType;
use App\Entity\Type\KycContentOwnership;
use App\Entity\Type\KycSendingType;
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
            'name' => self::faker()->name(),
            'account_type' => KycAccountType::BUSINESS,
            'country' => 'France',
            'address' => self::faker()->address(),
            'website' => self::faker()->url(),
            'content_ownership' => KycContentOwnership::SELF,
            'sending_type' => [KycSendingType::TRANSACTIONAL->value],
            'use_case' => self::faker()->sentence(),
            'status' => KycStatus::PENDING,
            'submitted_at' => new \DateTimeImmutable(),
        ];
    }

}
