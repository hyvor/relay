<?php

namespace App\Api\Sudo\Input;

use App\Util\OptionalPropertyTrait;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateIpAddressInput
{
    use OptionalPropertyTrait;

    #[Assert\Type('int')]
    public ?int $queue_id;

    #[Assert\Choice(choices: ['warming', 'warmed'])]
    public ?string $warmup_status;

    /**
     * @var array<int>|null
     */
    #[Assert\Type('array')]
    #[Assert\All(
        new Assert\Type('integer')
    )]
    public ?array $warmup_schedule;
}
