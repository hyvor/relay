<?php

namespace App\Api\Console\Object;

use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\SendFeedback;

class SendObject
{
    public int $id;
    public string $uuid;
    public int $created_at;
    public string $from_address;
    public ?string $from_name;
    public ?string $subject;
    public ?string $body_html;
    public ?string $body_text;
    /** @var array<string, string> */
    public array $headers;
    public string $raw;
    public int $size_bytes;
    public bool $queued;
    public int $send_after;

    /**
     * @var SendRecipientObject[]
     */
    public array $recipients = [];

    /**
     * @var SendAttemptObject[]
     */
    public array $attempts = [];

    /**
     * @var SendFeedbackObject[]
     */
    public array $feedback = [];

    /**
     * @param SendAttempt[] $attempts
     * @param SendFeedback[] $feedback
     */
    public function __construct(
        Send $send,
        array $attempts = [],
        array $feedback = [],
        bool $content = false
    ) {
        $this->id = $send->getId();
        $this->uuid = $send->getUuid();
        $this->created_at = $send->getCreatedAt()->getTimestamp();
        $this->from_address = $send->getFromAddress();
        $this->from_name = $send->getFromName();
        $this->subject = $send->getSubject();
        $this->body_html = $content ? $send->getBodyHtml() : null;
        $this->body_text = $content ? $send->getBodyText() : null;
        $this->headers = $send->getHeaders();
        $this->raw = $content ? $send->getRaw() : '';
        $this->size_bytes = $send->getSizeBytes();
        $this->queued = $send->getQueued();
        $this->send_after = $send->getSendAfter()->getTimestamp();

        $this->recipients = array_map(fn($recipient) => new SendRecipientObject($recipient),
            $send->getRecipients()->toArray());
        $this->attempts = array_map(fn(SendAttempt $attempt) => new SendAttemptObject($attempt), $attempts);
        $this->feedback = array_map(fn(SendFeedback $fb) => new SendFeedbackObject($fb), $feedback);
    }
}
