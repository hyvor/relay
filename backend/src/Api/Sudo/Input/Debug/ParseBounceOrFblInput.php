<?php

namespace App\Api\Sudo\Input\Debug;


use Symfony\Component\Validator\Constraints as Assert;

class ParseBounceOrFblInput
{

    #[Assert\NotBlank]
    public string $raw;

}