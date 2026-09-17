<?php

namespace App\Entity\Type;

enum KycSendingType: string
{

    case TRANSACTIONAL = 'transactional';
    case DISTRIBUTIONAL = 'distributional';


    /**
     * @return list<string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
