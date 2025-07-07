<?php

namespace App\Api\Console\Input;

use App\Api\Console\Authorization\Scope;
use App\Util\OptionalPropertyTrait;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateApiKeyInput
{
    use OptionalPropertyTrait;

    public bool $enabled;

    /**
     * @var string[]
     */
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
