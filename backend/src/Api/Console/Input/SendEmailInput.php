<?php

namespace App\Api\Console\Input;

use Symfony\Component\Validator\Constraints as Assert;

class SendEmailInput
{

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $from;

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