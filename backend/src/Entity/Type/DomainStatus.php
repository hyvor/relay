<?php

namespace App\Entity\Type;

// see https://relay.hyvor.com/docs/domains#status
enum DomainStatus: string
{

    case PENDING = 'pending';
    case ACTIVE = 'active';
    case WARNING = 'warning';
    case SUSPENDED = 'suspended';

    public function canSendEmails(): bool
    {
        return match ($this) {
            self::ACTIVE, self::WARNING => true,
            self::PENDING, self::SUSPENDED => false,
        };
    }

}