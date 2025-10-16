<?php

namespace App\Api\Console\Object;

use App\Entity\SendAttempt;
use App\Entity\Type\SendAttemptStatus;

class SendAttemptObject
{

    public int $id;
    public int $created_at;
    public SendAttemptStatus $status;
    public int $try_count;

    public string $domain;

    /**
     * @var string[]
     */
    public array $resolved_mx_hosts;
    public ?string $responded_mx_host = null;

    /**
     * @var array<string, mixed>
     */
    public array $smtp_conversations = [];
    public int $duration_ms;

    /**
     * @var array<SendAttemptRecipientObject>
     */
    public array $recipients = [];

    public function __construct(SendAttempt $attempt)
    {
        $this->id = $attempt->getId();
        $this->created_at = $attempt->getCreatedAt()->getTimestamp();
        $this->status = $attempt->getStatus();
        $this->try_count = $attempt->getTryCount();
        $this->domain = $attempt->getDomain();
        $this->resolved_mx_hosts = $attempt->getResolvedMxHosts();
        $this->responded_mx_host = $attempt->getRespondedMxHost();
        $this->smtp_conversations = $attempt->getSmtpConversations();
        $this->duration_ms = $attempt->getDurationMs();

        foreach ($attempt->getRecipients() as $recipient) {
            $this->recipients[] = new SendAttemptRecipientObject($recipient);
        }
    }

}
