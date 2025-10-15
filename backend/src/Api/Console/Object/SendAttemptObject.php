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
    /**
     * @var int[]
     */
    public array $recipient_ids = [];
    /**
     * @var array<int, string>
     */
    public array $recipient_statuses = [];
    public int $duration_ms;
    public ?string $error;

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
        $this->recipient_ids = $attempt->getRecipientIds();
        $this->recipient_statuses = $attempt->getRecipientStatuses();
        $this->duration_ms = $attempt->getDurationMs();
        $this->error = $attempt->getError();
    }

}
