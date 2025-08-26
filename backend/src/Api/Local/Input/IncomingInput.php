<?php

namespace App\Api\Local\Input;

use Symfony\Component\Validator\Constraints as Assert;

class IncomingInput
{
    #[Assert\NotBlank]
    public IncomingType $type;

    // Bounce
    #[Assert\When(
        expression: 'this.type.value === "bounce" && this.error === null',
        constraints: [
            new Assert\NotBlank(),
        ]
    )]
    public ?DsnInput $dsn = null;
    #[Assert\When(
        expression: 'this.type.value === "bounce" && this.error === null',
        constraints: [
            new Assert\NotBlank(),
        ]
    )]
    public ?string $bounce_uuid = null;

    // Complaint
    #[Assert\When(
        expression: 'this.type.value === "complaint" && this.error === null',
        constraints: [
            new Assert\NotBlank(),
        ]
    )]
    public ?ArfInput $arf = null;

    // Error
    #[Assert\When(
        expression: 'this.dsn === null && this.arf === null',
        constraints: [
            new Assert\NotBlank(),
        ]
    )]
    public ?string $error = null;


    // Debug Email
    #[Assert\NotBlank]
    public string $raw_email;
    #[Assert\NotBlank]
    public string $mail_from;
    #[Assert\NotBlank]
    public string $rcpt_to;
}
