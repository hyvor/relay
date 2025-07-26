<?php

namespace App\Api\Sudo\Input;

use App\Entity\Type\DnsRecordType;
use Symfony\Component\Validator\Constraints as Assert;

class CreateDnsRecordInput
{
    #[Assert\NotBlank]
    public DnsRecordType $type;

    public string $subdomain;

    #[Assert\NotBlank]
    public string $content;

    #[Assert\PositiveOrZero]
    public int $ttl = 300;

    #[Assert\PositiveOrZero]
    public int $priority = 0;
}
