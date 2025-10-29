<?php

namespace App\Tests\Factory;

use App\Entity\SendAttemptRecipient;
use App\Entity\Type\SendRecipientStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SendAttemptRecipient>
 */
final class SendAttemptRecipientFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return SendAttemptRecipient::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'send_attempt' => SendAttemptFactory::new(),
            'send_recipient_id' => 0,
            'smtp_code' => self::faker()->numberBetween(200, 500),
            'smtp_enhanced_code' => self::faker()->optional()->regexify('[2-5]\.[0-9]\.[0-9]'),
            'smtp_message' => self::faker()->sentence(),
            'recipient_status' => SendRecipientStatus::ACCEPTED,
            'is_suppressed' => false,
        ];
    }
}