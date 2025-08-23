<?php

namespace App\Tests\Factory;

use App\Entity\SendAttempt;
use App\Entity\Type\SendAttemptStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SendAttempt>
 */
final class SendAttemptFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return SendAttempt::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'send' => SendFactory::new(),
            'ip_address' => IpAddressFactory::new(),
            'resolved_mx_hosts' => [self::faker()->domainName()],
            'status' => SendAttemptStatus::ACCEPTED,
            'try_count' => self::faker()->numberBetween(0, 5),
            'responded_mx_host' => self::faker()->optional()->domainName(),
            'smtp_conversations' => [],
            'error' => self::faker()->optional()->sentence(),
        ];
    }
}
