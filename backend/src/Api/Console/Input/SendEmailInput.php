<?php

namespace App\Api\Console\Input;

use App\Api\Console\Validation\EmailAddress;
use Symfony\Component\Validator\Constraints as Assert;

class SendEmailInput
{

    /**
     * @var string|array{email: string, name?: string}
     */
    #[Assert\NotBlank]
    #[EmailAddress]
    public string|array $from;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $to;

    public string $subject = '';

    public ?string $body_html = null;

    public ?string $body_text = null;

    /**
     * @var array<string, string>
     */
    public array $headers = [];

}