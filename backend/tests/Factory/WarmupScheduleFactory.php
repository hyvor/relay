<?php

namespace App\Tests\Factory;

use App\Entity\Type\WarmupStatus;
use App\Entity\WarmupSchedule;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<WarmupSchedule>
 */
final class WarmupScheduleFactory extends PersistentObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return WarmupSchedule::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('today'),
            'sent_today' => 0,
            'max_today' => 0,
            'schedule' => array_fill(0, 30, 100),
            'results' => [],
        ];
    }

}
