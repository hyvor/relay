<?php

namespace App\Service\Domain;

class DkimVerificationResult
{

    public bool $verified;
    public \DateTimeImmutable $checkedAt;
    public ?string $errorMessage = null;

}