<?php

namespace App\Tests\Factory;

use App\Entity\Queue;
use App\Entity\Type\QueueType;
use App\Service\Queue\QueueService;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Queue>
 */
final class QueueFactory extends PersistentObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Queue::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->word() . '_queue',
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'type' => QueueType::TRANSACTIONAL,
        ];
    }

    public static function createDefault(QueueType $type): Queue
    {
        return self::createOne(['type' => $type, 'name' => QueueService::DEFAULT_QUEUE_NAME]);
    }

    public static function createTrial(QueueType $type): Queue
    {
        return self::createOne(['type' => $type, 'name' => QueueService::TRIAL_QUEUE_NAME]);
    }

    public static function createTransactional(): Queue
    {
        return self::createDefault(QueueType::TRANSACTIONAL);
    }

    public static function createDistributional(): Queue
    {
        return self::createDefault(QueueType::DISTRIBUTIONAL);
    }

}
