<?php

namespace App\Api\Console\Input;

use App\Api\Console\Validation\EmailAddress;
use App\Api\Console\Validation\Headers;
use App\Service\Email\EmailAddressFormat;
use Symfony\Component\Mime\Address;
use Symfony\Component\Validator\Constraints as Assert;

class SendEmailInput
{

    /**
     * @var string|array{email: string, name?: string}
     */
    #[Assert\NotBlank]
    #[EmailAddress]
    public string|array $from;

    /**
     * @var string|array{email: string, name?: string}
     */
    #[Assert\NotBlank]
    #[EmailAddress]
    public string|array $to;

    /**
     * A sensible limit of 998 characters, based on Google's header limits:
     * https://support.google.com/a/answer/14016360?hl=en&src=supportwidget
     */
    #[Assert\Length(max: 998)]
    public string $subject = '';

    #[Assert\When(
        expression: "this.body_text === null",
        constraints: [
            new Assert\NotBlank(message: 'body_html must not be blank if body_text is null'),
        ]
    )]
    public ?string $body_html = null;

    #[Assert\When(
        expression: "this.body_html === null",
        constraints: [
            new Assert\NotBlank(message: 'body_text must not be blank if body_html is null'),
        ]
    )]
    public ?string $body_text = null;

    /**
     * @var array<string, string>
     */
    #[Headers]
    public array $headers = [];

    public function getFromAddress(): Address
    {
        return EmailAddressFormat::createAddressFromInput($this->from);
    }

    public function getToAddress(): Address
    {
        return EmailAddressFormat::createAddressFromInput($this->to);
    }

}