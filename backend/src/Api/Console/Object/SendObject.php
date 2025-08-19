<?php

namespace App\Api\Console\Object;

use App\Entity\Send;
use App\Entity\SendAttempt;

class SendObject
{
    public int $id;
    public string $uuid;
    public int $created_at;
    public ?int $sent_at;
    public ?int $failed_at;
    public string $from_address;
    public ?string $from_name;
    public string $to_address;
    public ?string $subject;
    public ?string $body_html;
    public ?string $body_text;
    public string $raw;

    /**
     * @var SendRecipientObject[]
     */
    public array $recipients = [];

    /**
     * @var SendAttemptObject[]
     */
    public array $attempts = [];

    /**
     * @param SendAttempt[] $attempts
     */
    public function __construct(Send $send, array $attempts = [])
    {
        $this->id = $send->getId();
        $this->uuid = $send->getUuid();
        $this->created_at = $send->getCreatedAt()->getTimestamp();
        $this->from_address = $send->getFromAddress();
        $this->from_name = $send->getFromName();
        $this->subject = $send->getSubject();
        $this->body_html = $send->getBodyHtml();
        $this->body_text = $send->getBodyText();
        $this->raw = $send->getRaw();

        $this->recipients = array_map(fn($recipient) => new SendRecipientObject($recipient), $send->getRecipients());
        $this->attempts = array_map(fn(SendAttempt $attempt) => new SendAttemptObject($attempt), $attempts);
    }
}
