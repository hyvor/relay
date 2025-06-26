<?php

namespace App\Api\Console\Input;

use Symfony\Component\Validator\Constraints as Assert;

class DomainCreateInput
{

    #[Assert\NotBlank]
    public string $domain;

}