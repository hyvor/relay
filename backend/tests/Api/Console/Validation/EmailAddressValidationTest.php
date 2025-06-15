<?php

namespace Api\Console\Validation;

use App\Api\Console\Validation\EmailAddressValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @extends ConstraintValidatorTestCase<EmailAddressValidator>
 */
class EmailAddressValidationTest extends ConstraintValidatorTestCase
{

    protected function createValidator(): EmailAddressValidator
    {
        $mock = $this->createMock(ValidatorInterface::class);
        return new EmailAddressValidator($mock);
    }

}