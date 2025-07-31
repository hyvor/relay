<?php

namespace App\Tests\Factory;

use App\Entity\Project;
use App\Entity\Type\ProjectSendType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Project>
 */
final class ProjectFactory extends PersistentProxyObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Project::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'hyvor_user_id' => self::faker()->numberBetween(1, 10000),
            'name' => self::faker()->words(2, true),
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'send_type' => ProjectSendType::TRANSACTIONAL,
        ];
    }

}