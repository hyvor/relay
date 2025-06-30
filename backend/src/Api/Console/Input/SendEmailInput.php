<?php

namespace App\Api\Console\Input;

use App\Api\Console\Validation\EmailAddress;
use App\Api\Console\Validation\Headers;
use App\Service\Email\Dto\SendingAttachment;
use App\Service\Email\EmailAddressFormat;
use Symfony\Component\Mime\Address;
use Symfony\Component\Validator\Constraints as Assert;

class SendEmailInput
{

    /**
     * A sensible limit of 998 characters, based on Google's header limits:
     * https://support.google.com/a/answer/14016360?hl=en&src=supportwidget
     */
    private const MAX_SUBJECT_LENGTH = 998;
    private const MAX_BODY_LENGTH = 2 * 1024 * 1024; // 2MB

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


    #[Assert\Length(max: self::MAX_SUBJECT_LENGTH)]
    public string $subject = '';

    #[Assert\Length(max: self::MAX_BODY_LENGTH, maxMessage: 'body_html must not exceed 2MB.')]
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
    #[Assert\Length(max: self::MAX_BODY_LENGTH, maxMessage: 'body_text must not exceed 2MB.')]
    public ?string $body_text = null;

    /**
     * @var array<string, string>
     */
    #[Headers]
    public array $headers = [];

    /**
     * @var array<string, array{content: string, name: ?string, content_type: ?string}>
     */
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'content' => [
                    new Assert\NotBlank(),
                    new Assert\Type('string'),
                ],
                'content_type' => new Assert\Optional(new Assert\Type('string')),
                'name' => new Assert\Optional(new Assert\Type('string')),
            ],
            allowExtraFields: false
        )
    ])]
    #[Assert\Count(max: 10, maxMessage: 'You can attach a maximum of 10 files.')]
    public array $attachments = [];

    public function getFromAddress(): Address
    {
        return EmailAddressFormat::createAddressFromInput($this->from);
    }

    public function getToAddress(): Address
    {
        return EmailAddressFormat::createAddressFromInput($this->to);
    }

    /**
     * @return SendingAttachment[]
     */
    public function getAttachments(): array
    {
        $attachments = [];
        foreach ($this->attachments as $attachment) {
            $sendEmailAttachment = new SendingAttachment();
            $sendEmailAttachment->content = base64_decode($attachment['content']);
            $sendEmailAttachment->contentType = $attachment['content_type'] ?? null;
            $sendEmailAttachment->name = $attachment['name'] ?? null;
            $attachments[] = $sendEmailAttachment;
        }
        return $attachments;
    }

}