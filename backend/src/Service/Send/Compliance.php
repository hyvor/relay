<?php

namespace App\Service\Send;

class Compliance
{

    public const float BOUNCE_RATE_WARNING = 0.02; // 2%
    public const float BOUNCE_RATE_ERROR = 0.05; // 5%

    public const float COMPLAINT_RATE_WARNING = 0.001; // 0.1%
    public const float COMPLAINT_RATE_ERROR = 0.005; // 0.5%

    /**
     * @return array<string, float>
     */
    public static function getRates(): array
    {
        return [
            'bounce_rate_warning' => self::BOUNCE_RATE_WARNING,
            'bounce_rate_error' => self::BOUNCE_RATE_ERROR,
            'complaint_rate_warning' => self::COMPLAINT_RATE_WARNING,
            'complaint_rate_error' => self::COMPLAINT_RATE_ERROR,
        ];
    }

}