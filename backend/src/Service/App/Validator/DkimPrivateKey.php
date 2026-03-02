<?php

namespace App\Service\App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DkimPrivateKey extends Constraint {

    public string $message = 'The provided DKIM private key is invalid or poorly formatted.';
    public string $weakKeyMessage = 'The private key is too weak ({{ bits }} bits). Minimum 1024 bits required.';

    public const int MIN_BITS = 1024;

}
