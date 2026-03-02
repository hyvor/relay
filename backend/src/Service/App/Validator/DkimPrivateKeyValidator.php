<?php

namespace App\Service\App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DkimPrivateKeyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {

        // @codeCoverageIgnoreStart
        if (!$constraint instanceof DkimPrivateKey) {
            throw new UnexpectedTypeException($constraint, DkimPrivateKey::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
        // @codeCoverageIgnoreEnd

        // 1. Attempt to load the key
        $res = openssl_pkey_get_private($value);

        if ($res === false) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // 2. Check key strength and type
        $details = openssl_pkey_get_details($res);

        /** @var int $bits */
        $bits = is_array($details) ? $details['bits'] ?? 0 : 0;

        if ($bits < $constraint::MIN_BITS) {
            $this->context->buildViolation($constraint->weakKeyMessage)
                ->setParameter('{{ bits }}', (string) $bits)
                ->addViolation();
        }
    }
}
