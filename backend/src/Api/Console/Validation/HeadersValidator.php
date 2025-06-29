<?php

namespace App\Api\Console\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class HeadersValidator extends ConstraintValidator
{

    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof Headers) {
            throw new UnexpectedTypeException($constraint, Headers::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_array($value)) {
            $this->context->buildViolation('The headers must be an array.')
                ->setInvalidValue($value)
                ->addViolation();
            return;
        }

        foreach ($value as $key => $headerValue) {
            if (!is_string($key)) {
                $this->context->buildViolation('The header key {{ key }} must be a string.')
                    ->setInvalidValue($key)
                    ->setParameter('{{ key }}', (string) $key)
                    ->addViolation();
                continue;
            }
            if (!is_string($headerValue)) {
                $this->context->buildViolation('The header value of {{ key }} must be a string.')
                    ->setInvalidValue($headerValue)
                    ->setParameter('{{ key }}', $key)
                    ->addViolation();
                continue;
            }
        }
    }
}