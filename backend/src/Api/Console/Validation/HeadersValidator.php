<?php

namespace App\Api\Console\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class HeadersValidator extends ConstraintValidator
{

    // IMPORTANT! should be lowercase
    // document in SendEmails.svelte (/docs/send-emails#limits)
    // MUST BE kept in sync with custom_headers.go
    private const array UNALLOWED_HEADERS = [
        // emails
        'from',
        'to',
        'cc',
        'bcc',
        'sender',

        // other (already set by symfony)
        'date',
        'subject',
        'content-type',
        'mime-version',
        'content-transfer-encoding',
        'content-disposition',
        'message-id',

        // security
        'dkim-signature',
        'return-path',
        'x-mailer',
        'x-originating-ip',
        'authentication-results',
    ];

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

            $headerKey = strtolower($key);
            if (in_array($headerKey, self::UNALLOWED_HEADERS, true)) {
                $this->context->buildViolation('The header {{ key }} is not allowed as a custom header.')
                    ->setInvalidValue($key)
                    ->setParameter('{{ key }}', $key)
                    ->addViolation();
            }
        }
    }
}
