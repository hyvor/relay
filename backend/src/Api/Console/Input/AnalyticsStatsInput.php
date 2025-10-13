<?php

namespace App\Api\Console\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AnalyticsStatsInput
{
    #[Assert\Choice(choices: ['30d', '7d', '24h'])]
    public string $period = '30d';
}
