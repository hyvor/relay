<?php

namespace App\Api\Console\Input;

use Symfony\Component\Validator\Constraints as Assert;

class RetrySendInput
{
    public ?int $send_after = null;

    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('int'),
    ])]
    public ?array $recipient_ids = null;
}
