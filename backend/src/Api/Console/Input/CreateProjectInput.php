<?php

namespace App\Api\Console\Input;

use App\Entity\Type\ProjectSendType;
use Symfony\Component\Validator\Constraints as Assert;

class CreateProjectInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name;

    #[Assert\NotBlank]
    public ProjectSendType $send_type;
}