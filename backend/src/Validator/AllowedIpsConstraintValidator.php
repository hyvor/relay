<?php

namespace App\Validator;

use App\Service\ApiKey\AllowedIp;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class AllowedIpsConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AllowedIpsConstraint) {
            throw new UnexpectedTypeException($constraint, AllowedIpsConstraint::class);
        }

        if ($value === null) {
            return;
        }

        if (!is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        foreach ($value as $i => $entry) {
            if (!is_string($entry)) {
                $this->context->buildViolation('Allowed IP entry must be a string.')
                    ->atPath("[$i]")
                    ->addViolation();
                continue;
            }

            $error = AllowedIp::validateEntry($entry);
            if ($error !== null) {
                $this->context->buildViolation($error)
                    ->atPath("[$i]")
                    ->addViolation();
            }
        }
    }
}
