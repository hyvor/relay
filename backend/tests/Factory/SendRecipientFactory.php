<?php

namespace App\Tests\Factory;

use App\Entity\SendRecipient;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SendRecipientType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SendRecipient>
 */
final class SendRecipientFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return SendRecipient::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'send' => SendFactory::new(),
            'type' => SendRecipientType::TO,
            'address' => self::faker()->email(),
            'name' => self::faker()->name(),
            'status' => SendRecipientStatus::ACCEPTED,
            'is_suppressed' => false,
        ];
    }

}
