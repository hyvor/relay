<?php

namespace App\Service\Domain;

class DkimVerificationResult implements \JsonSerializable
{

    public bool $verified;
    public \DateTimeImmutable $checkedAt;
    public ?string $errorMessage = null;

    public function jsonSerialize(): mixed
    {
        return [
            'verified' => $this->verified,
            'checked_at' => $this->checkedAt->getTimestamp(),
            'error_message' => $this->errorMessage,
        ];
    }
}
