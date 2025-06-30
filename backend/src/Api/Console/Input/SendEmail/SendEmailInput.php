<?php

namespace App\Api\Console\Input\SendEmail;

use App\Api\Console\Validation\EmailAddress;
use App\Api\Console\Validation\Headers;
use App\Service\Send\Dto\SendingAttachment;
use App\Service\Send\EmailAddressFormat;
use App\Service\Send\SendLimits;
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


    #[Assert\Length(max: SendLimits::MAX_SUBJECT_LENGTH)]
    public string $subject = '';

    #[Assert\Length(max: SendLimits::MAX_BODY_LENGTH, maxMessage: 'body_html must not exceed 2MB.')]
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
    #[Assert\Length(max: SendLimits::MAX_BODY_LENGTH, maxMessage: 'body_text must not exceed 2MB.')]
    public ?string $body_text = null;

    /**
     * @var array<string, string>
     */
    #[Headers]
    public array $headers = [];

    /**
     * @var array<array{content: string, name: ?string, content_type: ?string}>
     */
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'content' => [
                    new Assert\NotBlank(),
                    new Assert\Type('string'),
                    new Assert\Length(max: SendLimits::MAX_EMAIL_SIZE, maxMessage: 'Attachment content must not exceed 10MB.'),
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
     * @throws UnableToDecodeAttachmentBase64Exception
     */
    public function getAttachments(): array
    {
        $attachments = [];
        foreach ($this->attachments as $i => $attachment) {
            $sendEmailAttachment = new SendingAttachment();

            $content = base64_decode($attachment['content'], true);
            if ($content === false) {
                throw new UnableToDecodeAttachmentBase64Exception($i);
            }

            $sendEmailAttachment->content = $content;
            $sendEmailAttachment->contentType = $attachment['content_type'] ?? null;
            $sendEmailAttachment->name = $attachment['name'] ?? null;
            $attachments[] = $sendEmailAttachment;
        }
        return $attachments;
    }

}