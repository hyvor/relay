<?php

namespace App\Api\Console\Input;

use App\Api\Console\Authorization\Scope;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateApiKeyInput
{
    public bool $enabled;

    /**
     * @var string[]
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Choice(callback: 'getWebhookEventValues'),
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
