<?php

namespace App\Api\Console\Input;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateApiKeyInput
{
    #[Assert\NotBlank]
    public bool $enabled;
}