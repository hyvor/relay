<?php

namespace App\Api\Local\Input;

use Symfony\Component\Validator\Constraints as Assert;

class IncomingFblInput
{
    #[Assert\When(
        'this.error == null',
        constraints: [
            new Assert\NotBlank(message: 'Either ARF or error must be provided'),
        ]
    )]
    public ?ArfInput $arf = null;

    #[Assert\When(
        'this.arf == null',
        constraints: [
            new Assert\NotBlank(message: 'Either ARF or error must be provided'),
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
