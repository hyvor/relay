<?php

namespace App\Api\Sudo\Input\Debug;


use App\Entity\Type\DebugIncomingEmailType;
use Symfony\Component\Validator\Constraints as Assert;

class ParseBounceOrFblInput
{

    #[Assert\NotBlank]
    public string $raw;

    public DebugIncomingEmailType $type;

}