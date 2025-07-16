<?php

namespace App\Api\Local\Input;

use App\Entity\Type\SendStatus;
use Symfony\Component\Validator\Constraints as Assert;

class SendDoneInput
{

    #[Assert\NotBlank]
    public int $sendId;

    #[Assert\NotBlank]
    #[Assert\Choice(['queued', 'processing', 'accepted', 'bounced', 'complained'], message: 'Invalid status.')]
    public string $status;

    #[Assert\Json]
    public string $result;

    public function getStatusEnum(): SendStatus
    {
        return SendStatus::from($this->status);
    }

}
