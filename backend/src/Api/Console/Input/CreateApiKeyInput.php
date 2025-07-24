<?php

namespace App\Api\Console\Input;

use App\Api\Console\Authorization\Scope;
use Symfony\Component\Validator\Constraints as Assert;

class CreateApiKeyInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name;

    /**
     * @var string[]
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Choice(callback: 'getScopeValues'),
    ])]
    public array $scopes;

    /**
     * @return string[]
     */
    public static function getScopeValues(): array
    {
        return array_column(Scope::cases(), 'value');
    }
}
