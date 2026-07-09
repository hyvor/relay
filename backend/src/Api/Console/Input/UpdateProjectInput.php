<?php

declare(strict_types=1);

namespace App\Api\Console\Input;

use App\Util\OptionalPropertyTrait;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateProjectInput
{
    use OptionalPropertyTrait;

    #[Assert\Length(min: 1, max: 255)]
    public string $name;
}
