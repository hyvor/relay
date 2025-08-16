<?php

namespace App\Tests\Api\Console\Validation;

use App\Api\Console\Validation\EmailAddress;
use App\Api\Console\Validation\EmailAddressValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;

/**
 * @extends ConstraintValidatorTestCase<EmailAddressValidator>
 */
#[CoversClass(EmailAddressValidator::class)]
class EmailAddressValidatorTest extends ConstraintValidatorTestCase
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


    // ignored
    #[TestWith([null])]
    #[TestWith([""])]
    // string
    #[TestWith(["supun@hyvor.com"])]
    // array with email
    #[TestWith([
        ["email" => "test@example.com"]
    ])]
    // array with email and name
    #[TestWith([
        [
            "email" => "test@example.com",
            "name" => "John Doe",
        ]
    ])]
    // array with email and empty name
    #[TestWith([
        [
            "email" => "test@example.com",
            "name" => "",
        ]
    ])]
    // array with email and null name
    #[TestWith([
        [
            "email" => "test@example.com",
            "name" => null,
        ]
    ])]
    public function test_no_violations(
        mixed $value
    ): void {
        $constraint = new EmailAddress();
        $this->validator->validate($value, $constraint);
        $this->assertNoViolation();
    }

    // invalid email
    #[TestWith(["invalid-email", "This value is not a valid email address.", "\"invalid-email\""])]
    // empty string with whitespace
    #[TestWith(["    ", "This value is not a valid email address.", '"    "'])]
    // array with invalid email
    #[TestWith([["email" => "invalid-email"], "This value is not a valid email address.", '"invalid-email"'])]
    // invalid name type in array
    #[TestWith([["email" => "supun@hyvor.com", "name" => 123], "The name must be a string.", null, 123])]
    // empty email in array
    #[TestWith([["email" => ""], "This value should not be blank.", '""'])]
    // null email in array
    #[TestWith([["email" => null], "This value should not be blank.", 'null'])]
    public function test_violation(
        mixed $value,
        string $violationMessage,
        ?string $violationParameterValue = null,
        mixed $invalidValue = null,
        string $violationParameter = "{{ value }}",
        bool $multiple = false
    ): void {
        $constraint = new EmailAddress(multiple: $multiple);
        $this->validator->validate($value, $constraint);

        $violation = $this->buildViolation($violationMessage);

        if ($violationParameterValue !== null) {
            $violation->setParameter($violationParameter, $violationParameterValue);
        }
        if ($invalidValue !== null) {
            $violation->setInvalidValue($invalidValue);
        }

        $violation->assertRaised();
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

        $this->buildViolation("Multiple email addresses are not allowed.")
            ->setInvalidValue([])
            ->assertRaised();
    }


    // MULTIPLE EMAILS

    public function test_does_not_allow_multiple_emails_by_default(): void
    {
        $constraint = new EmailAddress();
        $value = ["supun@hyvor.com"];

        $this->validator->validate($value, $constraint);

        $this->buildViolation('Multiple email addresses are not allowed.')
            ->setInvalidValue($value)
            ->assertRaised();
    }


    // passing
    #[TestWith([[]])]
    #[TestWith([["supun@hyvor.com"]])]
    #[TestWith([["supun@hyvor.com", "ishini@hyvor.com"]])]
    #[TestWith([[["email" => "supun@hyvor.com"], "ishini@hyvor.com"]])]
    // failing
    #[TestWith([["invalid"], "This value is not a valid email address.", '"invalid"'])]
    #[TestWith([[['email' => 'invalid(']], "This value is not a valid email address.", '"invalid("'])]
    public function test_validates_multiple_emails_when_multiple_is_true(
        mixed $value,
        ?string $message = null,
        string $violationParameterValue = ''
    ): void {
        $constraint = new EmailAddress(multiple: true);
        $this->validator->validate($value, $constraint);

        if ($message !== null) {
            $this->buildViolation($message)
                ->setParameter("{{ value }}", $violationParameterValue)
                ->assertRaised();
        } else {
            $this->assertNoViolation();
        }
    }

    public function test_does_not_allow_nested_arrays(): void
    {
        $constraint = new EmailAddress(multiple: true);
        $value = [
            [
                ['email' => 'supun@hyvor.com', 'name' => 'Supun'],
            ]
        ];
        $constraint = new EmailAddress(multiple: true);
        $this->validator->validate($value, $constraint);

        $this->buildViolation('Multiple email addresses are not allowed.')
            ->setInvalidValue([
                ['email' => 'supun@hyvor.com', 'name' => 'Supun'],
            ])
            ->assertRaised();
    }

}
