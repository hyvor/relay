<?php

namespace App\Tests\Validator;

use App\Service\ApiKey\AllowedIp;
use App\Validator\AllowedIpsConstraint;
use App\Validator\AllowedIpsConstraintValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AllowedIpsConstraintValidator>
 */
#[CoversClass(AllowedIpsConstraint::class)]
#[CoversClass(AllowedIpsConstraintValidator::class)]
#[CoversClass(AllowedIp::class)]
class AllowedIpsConstraintValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): AllowedIpsConstraintValidator
    {
        return new AllowedIpsConstraintValidator();
    }

    public function test_null_is_skipped(): void
    {
        $this->validator->validate(null, new AllowedIpsConstraint());
        $this->assertNoViolation();
    }

    public function test_empty_array_is_valid(): void
    {
        $this->validator->validate([], new AllowedIpsConstraint());
        $this->assertNoViolation();
    }

    public function test_valid_entries_pass(): void
    {
        $this->validator->validate(
            ['203.0.113.5', '198.51.100.0/24', '2001:db8::/64'],
            new AllowedIpsConstraint()
        );
        $this->assertNoViolation();
    }

    public function test_invalid_entry_emits_violation_with_index_path(): void
    {
        $this->validator->validate(
            ['203.0.113.5', '10.0.0.1'],
            new AllowedIpsConstraint()
        );
        $this->buildViolation("'10.0.0.1' is in a private, CGNAT, or otherwise reserved range.")
            ->atPath('property.path[1]')
            ->assertRaised();
    }

    public function test_non_string_entry_emits_violation(): void
    {
        $this->validator->validate([42], new AllowedIpsConstraint());
        $this->buildViolation('Allowed IP entry must be a string.')
            ->atPath('property.path[0]')
            ->assertRaised();
    }

    public function test_wrong_constraint_type_throws(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate([], new NotBlank());
    }

    public function test_non_array_value_throws(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate('not-an-array', new AllowedIpsConstraint());
    }
}
