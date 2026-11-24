<?php

namespace App\Api\Sudo\Input\Queue;

use App\Entity\Type\QueueType;
use Symfony\Component\Validator\Constraints as Assert;

class CreateQueueInput
{
    #[Assert\NotBlank]
    public QueueType $type;

    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^[a-z0-9_-]{1,64}$/',
        message: 'Name can only contain lowercase letters, numbers, hyphens and underscores (max 64 characters).'
    )]
    public string $name;
}
