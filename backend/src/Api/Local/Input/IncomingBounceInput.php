<?php

namespace App\Api\Local\Input;

use Symfony\Component\Validator\Constraints as Assert;

class IncomingBounceInput
{
    #[Assert\When(
        'this.error == null',
        constraints: [
            new Assert\NotBlank(message: 'Either DSN or error must be provided'),
        ]
    )]
    public ?DsnInput $dsn = null;

    #[Assert\When(
        'this.error == null',
        constraints: [
            new Assert\NotBlank(message: 'bounce_uuid must be provided when DSN is provided'),
        ]
    )]
    public ?string $bounce_uuid = null;

    #[Assert\When(
        'this.dsn == null',
        constraints: [
            new Assert\NotBlank(message: 'Either DSN or error must be provided'),
        ]
    )]
    public ?string $error = null;

    #[Assert\NotBlank]
    public string $raw_email;
    #[Assert\NotBlank]
    public string $mail_from;
    #[Assert\NotBlank]
    public string $rcpt_to;
}

