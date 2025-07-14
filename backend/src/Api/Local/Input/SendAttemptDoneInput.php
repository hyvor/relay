<?php

namespace App\Api\Local\Input;

use Symfony\Component\Validator\Constraints as Assert;

class SendAttemptDoneInput
{

    /**
     * @var array<int>
     */
    #[Assert\NotBlank]
    #[Assert\All(
        new Assert\Type('integer')
    )]
    public array $send_attempt_ids;


}