<?php

namespace App\Tests\Api\Console\Validation;

use App\Api\Console\Validation\Headers;
use App\Api\Console\Validation\HeadersValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<HeadersValidator>
 */
#[CoversClass(HeadersValidator::class)]
#[CoversClass(Headers::class)]
class HeadersValidationTest extends ConstraintValidatorTestCase
{

    protected function createValidator(): HeadersValidator
    {
        return new HeadersValidator();
    }

    public function testValidateWithWrongConstraintType(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $wrongConstraint = $this->createMock(Constraint::class);
        $this->validator->validate(["key" => "value"], $wrongConstraint);
    }

    public function testValidateWithNullValue(): void
    {
        $constraint = new Headers();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithEmptyStringValue(): void
    {
        $constraint = new Headers();

        $this->validator->validate("", $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithValidHeaders(): void
    {
        $constraint = new Headers();
        $headers = [
            "custom-header" => "value",
            "another-header" => "another value",
        ];

        $this->validator->validate($headers, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateWithNonArrayValue(): void
    {
        $constraint = new Headers();
        $value = "not-an-array";

        $this->validator->validate($value, $constraint);

        $this->buildViolation("The headers must be an array.")
            ->setInvalidValue($value)
            ->assertRaised();
    }

    public function testValidateWithNonStringKey(): void
    {
        $constraint = new Headers();
        $headers = [
            123 => "value",
        ];

        $this->validator->validate($headers, $constraint);

        $this->buildViolation("The header key {{ key }} must be a string.")
            ->setInvalidValue(123)
            ->setParameter("{{ key }}", "123")
            ->assertRaised();
    }

    public function testValidateWithNonStringValue(): void
    {
        $constraint = new Headers();
        $headers = [
            "custom-header" => 123,
        ];

        $this->validator->validate($headers, $constraint);

        $this->buildViolation("The header value of {{ key }} must be a string.")
            ->setInvalidValue(123)
            ->setParameter("{{ key }}", "custom-header")
            ->assertRaised();
    }

    // test the most important ones
    #[TestWith(['from'])]
    #[TestWith(['to'])]
    #[TestWith(['cc'])]
    #[TestWith(['bcc'])]
    #[TestWith(['sender'])]
    #[TestWith(['dkim-signature'])]
    public function testValidateWithUnallowedHeader(string $header): void
    {
        $constraint = new Headers();
        $headers = [
            $header => "value",
        ];

        $this->validator->validate($headers, $constraint);

        $this->buildViolation("The header {{ key }} is not allowed as a custom header.")
            ->setInvalidValue($header)
            ->setParameter("{{ key }}", $header)
            ->assertRaised();
    }

    public function testValidateWithMultipleViolations(): void
    {
        $constraint = new Headers();
        $headers = [
            123 => 456,
            "from" => "value",
        ];

        $this->validator->validate($headers, $constraint);

        $this->buildViolation("The header key {{ key }} must be a string.")
            ->setInvalidValue(123)
            ->setParameter("{{ key }}", "123")
            ->buildNextViolation("The header {{ key }} is not allowed as a custom header.")
            ->setInvalidValue("from")
            ->setParameter("{{ key }}", "from")
            ->assertRaised();
    }

}