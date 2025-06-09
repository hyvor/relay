<?php

namespace App\Tests\Factory;

use App\Entity\Send;
use App\Entity\Project;
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
            'email' => self::faker()->optional(0.9)->email(),
            'content_html' => self::faker()->optional(0.8)->randomHtml(),
            'content_text' => self::faker()->optional(0.7)->text(500),
            'from' => self::faker()->optional(0.9)->email(),
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

}