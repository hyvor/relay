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
            'secret_encrypted' => self::TEST_ENCRYPTED_SECRET
        ];
    }

    private const TEST_ENCRYPTED_SECRET = "eyJpdiI6Im1zVUJkNmdNbFhxRldQWVA5aGFwZXc9PSIsInZhbHVlIjoiUi9UTU9qcVhiZWlhQTArMDdjNzNBSTN0clgxNjNxU0FtMDhDY0NYQ1RrSjF4THFUQ2g1STRPVE8rQ25MYVBHMyIsIm1hYyI6IjlhMTBiNzkwNjMwYmIzN2Q1OTY4MzRiMGI1ZTZhNzQzMDQ4NjkzMWI3MjE4YWZjYTRhM2U5MzBhMmNiOWQ1OGUiLCJ0YWciOiIifQ==";

}
