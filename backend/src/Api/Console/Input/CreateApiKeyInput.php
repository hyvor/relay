<?php

namespace App\Api\Console\Input;

use App\Api\Console\Authorization\Scope;
use App\Validator\AllowedIpsConstraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
     * @var string[]
     */
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('string'),
    ])]
    #[AllowedIpsConstraint]
    public array $allowed_ips = [];

    /**
     * @return string[]
     */
    public static function getScopeValues(): array
    {
        return array_column(Scope::cases(), 'value');
    }

    #[Assert\Callback]
    public function validateAllowedIpsRequiredForSendsSend(ExecutionContextInterface $context): void
    {
        if (!isset($this->scopes) || !is_array($this->scopes)) {
            return;
        }

        if (in_array(Scope::SENDS_SEND->value, $this->scopes, true) && count($this->allowed_ips) === 0) {
            $context->buildViolation('At least one allowed IP is required when the "sends.send" scope is enabled.')
                ->atPath('allowed_ips')
                ->addViolation();
        }
    }
}
