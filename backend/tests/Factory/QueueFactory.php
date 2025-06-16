<?php

namespace App\Tests\Factory;

use App\Entity\Queue;
use App\Entity\Type\QueueType;
use App\Service\Queue\QueueService;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Queue>
 */
final class QueueFactory extends PersistentProxyObjectFactory
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
            'type' => QueueType::DEFAULT
        ];
    }

    public static function createTransactional(): Queue
    {
        return self::createOne(['name' => QueueService::TRANSACTIONAL_QUEUE_NAME]);
    }

    public static function createDistributional(): Queue
    {
        return self::createOne(['name' => QueueService::DISTRIBUTIONAL_QUEUE_NAME]);
    }

}
