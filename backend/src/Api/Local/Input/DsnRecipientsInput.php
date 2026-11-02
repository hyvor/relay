<?php

namespace App\Api\Local\Input;

use App\Entity\Type\BounceReason;
use Symfony\Component\Validator\Constraints as Assert;

class DsnRecipientsInput
{
    #[Assert\NotBlank]
    public string $EmailAddress;

    #[Assert\NotBlank]
    public string $Status;

    #[Assert\NotBlank]
    public string $Action;

    public ?BounceReason $BounceReason = null;
}
