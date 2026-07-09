<?php

declare(strict_types=1);

namespace App\Api\Console\Input\ProjectUser;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProjectUserInput
{
    #[Assert\NotBlank]
    public int $user_id;

    /**
     * @var string[]
     */
    #[Assert\NotBlank]
    public array $scopes;
}
