<?php

namespace App\Api\Console\Input;

use App\Api\Console\Validation\EmailAddress;
use App\Service\Email\EmailBuilder;
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
     * @var array<string|array{email: string, name?: string}>
     */
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\NotBlank(),
        new EmailAddress()
    ])]
    public array $to;

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
    public array $headers = [];

    public function getFromAddress(): Address
    {
        return EmailBuilder::createAddressFromInput($this->from);
    }

}