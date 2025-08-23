<?php

namespace App\Api\Console\Input\ProjectUser;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProjectUserInput
{
    #[Assert\NotBlank]
    public int $user_id;

    #[Assert\NotBlank]
    /**
     * @var string[]
     */
    public array $scopes;
}
