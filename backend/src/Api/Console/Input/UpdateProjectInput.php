<?php

namespace App\Api\Console\Input;

use App\Util\OptionalPropertyTrait;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateProjectInput
{
    use OptionalPropertyTrait;

    #[Assert\Length(max: 255)]
    public string $name;
} 