<?php

namespace App\Service\Kyc;

use Symfony\Component\Intl\Countries as IntlCountries;

class Countries
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_values(IntlCountries::getNames('en'));
    }
}
