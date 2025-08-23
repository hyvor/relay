<?php

namespace App\Tests\Factory;

use App\Entity\ServerTask;
use App\Entity\Type\ServerTaskType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ServerTask>
 */
final class ServerTaskFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return ServerTask::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'server' => ServerFactory::createOne(),
            'type' => ServerTaskType::UPDATE_STATE,
            'payload' => [],
        ];
    }
}
