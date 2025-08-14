<?php

namespace App\Tests\Factory;

use App\Api\Console\Authorization\Scope;
use App\Entity\Project;
use App\Entity\ProjectUser;
use App\Entity\Type\ProjectSendType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ProjectUser>
 */
class ProjectUserFactory extends PersistentProxyObjectFactory
{


    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return ProjectUser::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'user_id' => self::faker()->numberBetween(1, 10000),
            'project' => ProjectFactory::new(),
            'scopes' => Scope::all(),
        ];
    }


}