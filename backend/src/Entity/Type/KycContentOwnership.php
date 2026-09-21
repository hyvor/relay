<?php

namespace App\Entity\Type;

enum KycContentOwnership: string
{
    case SELF = 'self';
    case THIRD_PARTY = 'third_party';

    /**
     * @return list<string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
