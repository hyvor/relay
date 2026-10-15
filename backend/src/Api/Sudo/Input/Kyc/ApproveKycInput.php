<?php

namespace App\Api\Sudo\Input\Kyc;

use Symfony\Component\Validator\Constraints as Assert;

class ApproveKycInput
{
    #[Assert\Length(max: 2000)]
    public ?string $note = null;
}
