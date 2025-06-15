<?php

namespace App\Api\Console\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class EmailAddressValidator extends ConstraintValidator
{

    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof EmailAddress) {
            throw new UnexpectedTypeException($constraint, EmailAddress::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (is_string($value)) {
            $this->validateEmail($value);
        } elseif (is_array($value)) {
            $email = $value['email'] ?? null;
            $this->validateEmail($email);

            if (isset($value['name']) && !is_string($value['name'])) {
                $this->context->buildViolation('The name must be a string.')
                    ->setInvalidValue($value['name'])
                    ->addViolation();
            }
        } else {
            throw new UnexpectedValueException($value, 'string|array');
        }
    }

    private function validateEmail(mixed $value): void
    {

        $violations = $this->validator->validate($value, [
            new Assert\NotBlank(),
            new Assert\Type(type: 'string'),
            new Assert\Email(),
        ]);

        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->context->buildViolation($violation->getMessage())
                    ->setParameters($violation->getParameters())
                    ->addViolation();
            }
        }

    }


}