<?php

namespace App\Tests\Factory;

use App\Entity\Send;
use App\Entity\Project;
use App\Entity\Type\SendStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Send>
 */
final class SendFactory extends PersistentProxyObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Send::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'project' => ProjectFactory::new(),
            'uuid' => self::faker()->uuid(),
            'status' => self::faker()->randomElement(SendStatus::class),
            'to_address' => self::faker()->email(),
            'from_address' => self::faker()->email(),
            'raw' => self::faker()->text(),
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

}