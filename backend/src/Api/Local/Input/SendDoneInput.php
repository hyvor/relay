<?php

namespace App\Api\Local\Input;

use App\Entity\Type\SendStatus;
use Symfony\Component\Validator\Constraints as Assert;

class SendDoneInput
{

    #[Assert\NotBlank]
    public int $sendId;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['sent', 'failed'], message: 'Invalid status.')]
    public string $status;

    #[Assert\NotBlank]
    #[Assert\Json]
    public string $result;

    public function getStatus(): SendStatus
    {
        return SendStatus::from($this->status);
    }

}