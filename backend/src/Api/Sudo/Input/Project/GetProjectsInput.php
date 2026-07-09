<?php

namespace App\Api\Sudo\Input\Project;

use Symfony\Component\Validator\Constraints as Assert;

class GetProjectsInput
{

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 50;

    public ?int $before_id = null;

    public ?string $search = null;

    public ?int $organization_id = null;

}
