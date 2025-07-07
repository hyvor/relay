<?php

namespace App\Tests\Factory;

use App\Entity\Send;
use App\Entity\Type\SendStatus;
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
            "uuid" => self::faker()->uuid(),
            "project" => ProjectFactory::new(),
            "domain" => DomainFactory::new(),
            "queue" => QueueFactory::new(),
            "status" => SendStatus::QUEUED,
            "from_address" => self::faker()->email(),
            "from_name" => self::faker()->optional(0.7)->name(),
            "to_address" => self::faker()->email(),
            "to_name" => self::faker()->optional(0.7)->name(),
            "subject" => self::faker()->optional(0.8)->sentence(),
            "body_html" => self::faker()->optional(0.8)->randomHtml(),
            "body_text" => self::faker()->optional(0.7)->text(500),
            "raw" => self::faker()->text(1000),
            'message_id' => self::faker()->uuid(),
            "created_at" => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTime()
            ),
            "updated_at" => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTime()
            ),
            'send_after' => new \DateTimeImmutable()
        ];
    }

}
