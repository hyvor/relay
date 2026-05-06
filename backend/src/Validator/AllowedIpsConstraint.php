<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class AllowedIpsConstraint extends Constraint
{
    public string $message = '{{ error }}';

    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
