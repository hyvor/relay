<?php

namespace App\Api\Console\Input;

use App\Entity\Type\ApiKeyScope;
use Symfony\Component\Validator\Constraints as Assert;

class CreateApiKeyInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name;

    /**
     * @var string[] $scopes
     */
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
    ])]
    public array $scopes;
}
