<?php

namespace App\Api\Sudo\Input\Kyc;

use Symfony\Component\Validator\Constraints as Assert;

class RejectKycInput
{
    #[Assert\Length(max: 2000)]
    public ?string $note = null;

    #[Assert\Length(max: 2000)]
    public ?string $reject_reason = null;
}
