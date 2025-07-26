<?php

namespace App\Api\Sudo\Input;

use Symfony\Component\Validator\Constraints as Assert;

class CreateDnsRecordInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    public string $type;

    public string $subdomain;

    #[Assert\NotBlank]
    public string $content;

    #[Assert\PositiveOrZero]
    public int $ttl = 300;

    #[Assert\PositiveOrZero]
    public int $priority = 0;
}
