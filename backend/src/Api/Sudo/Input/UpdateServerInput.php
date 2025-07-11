<?php

namespace App\Api\Sudo\Input;

use App\Util\OptionalPropertyTrait;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateServerInput
{
    use OptionalPropertyTrait;

    #[Assert\Type('int')]
    #[Assert\PositiveOrZero]
    public int $api_workers;

    #[Assert\Type('int')]
    #[Assert\PositiveOrZero]
    public int $email_workers;

    #[Assert\Type('int')]
    #[Assert\PositiveOrZero]
    public int $webhook_workers;
} 
