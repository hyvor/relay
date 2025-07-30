<?php

namespace App\Api\Sudo\Input\Debug;


use App\Entity\Type\DebugIncomingEmailsType;
use Symfony\Component\Validator\Constraints as Assert;

class ParseBounceOrFblInput
{

    #[Assert\NotBlank]
    public string $raw;

    public DebugIncomingEmailsType $type;

}