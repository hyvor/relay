<?php

namespace App\Tests\Factory;

use App\Entity\Webhook;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Webhook>
 */
final class WebhookFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Webhook::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => new \DateTimeImmutable(),
            'updated_at' => new \DateTimeImmutable(),
            'url' => self::faker()->url(),
            'description' => self::faker()->text(),
            'project' => ProjectFactory::new(),
            'events' => [],
            'secret_encrypted' => bin2hex(random_bytes(16))
        ];
    }

}
