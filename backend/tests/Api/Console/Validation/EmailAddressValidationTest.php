<?php

namespace Api\Console\Validation;

use App\Api\Console\Validation\EmailAddress;
use App\Api\Console\Validation\EmailAddressValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;

/**
 * @extends ConstraintValidatorTestCase<EmailAddressValidator>
 */
class EmailAddressValidationTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): EmailAddressValidator
    {
        return new EmailAddressValidator(Validation::createValidator());
    }

    public function testValidateWithWrongConstraintType(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $wrongConstraint = $this->createMock(Constraint::class);
        $this->validator->validate("test@example.com", $wrongConstraint);
    }

    public function testValidateWithNullValue(): void
    {
        $constraint = new EmailAddress();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithEmptyStringValue(): void
    {
        $constraint = new EmailAddress();

        $this->validator->validate("", $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithValidStringEmail(): void
    {
        $constraint = new EmailAddress();
        $email = "test@example.com";

        $this->validator->validate($email, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithInvalidStringEmail(): void
    {
        $constraint = new EmailAddress();
        $email = "invalid-email";

        $this->validator->validate($email, $constraint);

        $this->buildViolation("This value is not a valid email address.")
            ->setParameter("{{ value }}", "\"$email\"")
            ->assertRaised();
    }

    public function testValidateWithBlankStringEmail(): void
    {
        $constraint = new EmailAddress();
        $email = "   ";

        $this->validator->validate($email, $constraint);

        $this->buildViolation("This value is not a valid email address.")
            ->setParameter("{{ value }}", "\"$email\"")
            ->assertRaised();
    }

    public function testValidateWithValidArrayEmailOnly(): void
    {
        $constraint = new EmailAddress();
        $value = ["email" => "test@example.com"];

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithValidArrayEmailAndName(): void
    {
        $constraint = new EmailAddress();
        $value = [
            "email" => "test@example.com",
            "name" => "John Doe",
        ];

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithArrayInvalidEmail(): void
    {
        $constraint = new EmailAddress();
        $value = ["email" => "invalid-email"];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("This value is not a valid email address.")
            ->setParameter("{{ value }}", "\"invalid-email\"")
            ->assertRaised();
    }

    public function testValidateWithArrayMissingEmail(): void
    {
        $constraint = new EmailAddress();
        $value = ["name" => "John Doe"];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("This value should not be blank.")
            ->setParameter("{{ value }}", "null")
            ->assertRaised();
    }

    public function testValidateWithArrayInvalidNameType(): void
    {
        $constraint = new EmailAddress();
        $value = [
            "email" => "test@example.com",
            "name" => 123,
        ];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("The name must be a string.")
            ->setInvalidValue(123)
            ->assertRaised();
    }

    public function testValidateWithArrayInvalidEmailAndInvalidName(): void
    {
        $constraint = new EmailAddress();
        $value = [
            "email" => "invalid-email",
            "name" => 123,
        ];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("This value is not a valid email address.")
            ->setParameter("{{ value }}", "\"invalid-email\"")
            ->buildNextViolation('The name must be a string.')
            ->setInvalidValue(123)
            ->assertRaised();
    }

    public function testValidateWithInvalidValueType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Expected argument of type "string|array", "int" given'
        );

        $constraint = new EmailAddress();
        $this->validator->validate(123, $constraint);
    }

    public function testValidateWithObjectValue(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $constraint = new EmailAddress();
        $value = new \stdClass();
        $this->validator->validate($value, $constraint);
    }

    public function testValidateWithBooleanValue(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $constraint = new EmailAddress();
        $this->validator->validate(true, $constraint);
    }

    public function testValidateWithNonStringEmailInArray(): void
    {
        $constraint = new EmailAddress();
        $value = ["email" => 123];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("This value should be of type string.")
            ->setParameters([
                "{{ value }}" => "123",
                "{{ type }}" => "string",
            ])

            ->buildNextViolation('This value is not a valid email address.')
            ->setParameter("{{ value }}", "\"123\"")

            ->assertRaised();
    }

    public function testValidateWithEmptyArray(): void
    {
        $constraint = new EmailAddress();
        $value = [];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("This value should not be blank.")
            ->setParameter("{{ value }}", "null")
            ->assertRaised();
    }

    public function testValidateWithNullEmailInArray(): void
    {
        $constraint = new EmailAddress();
        $value = ["email" => null];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("This value should not be blank.")
            ->setParameter("{{ value }}", "null")
            ->assertRaised();
    }

    public function testValidateWithEmptyStringEmailInArray(): void
    {
        $constraint = new EmailAddress();
        $value = ["email" => ""];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("This value should not be blank.")
            ->setParameter("{{ value }}", "\"\"")
            ->assertRaised();
    }

    public function testValidateWithWhitespaceOnlyEmailInArray(): void
    {
        $constraint = new EmailAddress();
        $value = ["email" => "   "];

        $this->validator->validate($value, $constraint);

        $this->buildViolation("This value is not a valid email address.")
            ->setParameter("{{ value }}", "\"   \"")
            ->assertRaised();
    }

    public function testValidateWithValidEmailButEmptyName(): void
    {
        $constraint = new EmailAddress();
        $value = [
            "email" => "test@example.com",
            "name" => "",
        ];

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithValidEmailButNullName(): void
    {
        $constraint = new EmailAddress();
        $value = [
            "email" => "test@example.com",
            "name" => null,
        ];

        $this->validator->validate($value, $constraint);
        $this->assertNoViolation();
    }
}
