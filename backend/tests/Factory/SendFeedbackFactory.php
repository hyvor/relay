<?php

namespace App\Tests\Factory;

use App\Entity\SendFeedback;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendFeedbackType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SendFeedback>
 */
final class SendFeedbackFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return SendFeedback::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'type' => SendFeedbackType::BOUNCE,
            'sendRecipient' => SendRecipientFactory::new(),
            'debugIncomingEmail' => DebugIncomingEmailFactory::new(),
        ];
    }
}
