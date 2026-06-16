<?php

namespace App\Api\Sudo\Input;

use Symfony\Component\Validator\Constraints as Assert;

class SudoStatsInput
{
    #[Assert\Choice(choices: ['30d', '7d', '24h'])]
    public string $period = '24h';
}
