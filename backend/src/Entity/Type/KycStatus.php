<?php

namespace App\Entity\Type;

enum KycStatus: string
{

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case STALE = 'stale';

    /**
     * @return list<string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
