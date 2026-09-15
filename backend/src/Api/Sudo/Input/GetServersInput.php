<?php

namespace App\Api\Sudo\Input;

use Symfony\Component\Validator\Constraints as Assert;

class GetServersInput
{

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 10;

    public ?int $before_id = null;

    public ?string $search = null;

}
