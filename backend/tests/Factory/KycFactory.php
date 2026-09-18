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
            'account_type' => KycAccountType::BUSINESS,
            'name' => self::faker()->name(),
            'country' => 'France',
            'address' => self::faker()->address(),
            'website' => self::faker()->url(),
            'email' => self::faker()->email(),
            'content_ownership' => KycContentOwnership::SELF,
            'sending_type' => [KycSendingType::TRANSACTIONAL->value],
            'use_case' => self::faker()->sentence(),
            'status' => KycStatus::PENDING,
        ];
    }

}
