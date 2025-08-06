<?php

namespace App\Api\Sudo\Input;

use App\Util\OptionalPropertyTrait;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateIpAddressInput
{
    use OptionalPropertyTrait;

    #[Assert\Type('int')]
    public ?int $queue_id;
}
