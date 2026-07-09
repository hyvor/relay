<?php

namespace App\Api\Sudo\Input\Project;

use Symfony\Component\Validator\Constraints as Assert;

class GetProjectOrganizationsInput
{

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 50;

    public ?int $before_id = null;

}
