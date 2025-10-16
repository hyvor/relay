<?php

namespace App\Tests\Factory;

use App\Entity\Type\WebhookDeliveryStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Entity\WebhookDelivery;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<WebhookDelivery>
 */
final class WebhookDeliveryFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return WebhookDelivery::class;
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
            'event' => WebhooksEventEnum::DOMAIN_CREATED,
            'status' => WebhookDeliveryStatus::PENDING,
            'request_body' => self::faker()->text(),
            'response' => self::faker()->text(),
            'send_after' => new \DateTimeImmutable(),
            'signature' => self::faker()->sha256(),
        ];
    }

}
