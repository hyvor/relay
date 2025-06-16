<?php

namespace App\Api\Console\Validation;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
/**
 * Email address as a string or as an object with email and name
 */
class EmailAddress extends Constraint
{
    public string $message = 'The email address "{{ value }}" is not valid.';
}