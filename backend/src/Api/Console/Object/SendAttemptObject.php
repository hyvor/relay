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

    /**
     * @var string[]
     */
    public array $resolved_mx_hosts;
    public ?string $accepted_mx_host = null;

    /**
     * @var array<string, mixed>
     */
    public array $smtp_conversations = [];
    public ?string $error;

    public function __construct(SendAttempt $attempt)
    {
        $this->id = $attempt->getId();
        $this->created_at = $attempt->getCreatedAt()->getTimestamp();
        $this->status = $attempt->getStatus();
        $this->try_count = $attempt->getTryCount();
        $this->resolved_mx_hosts = $attempt->getResolvedMxHosts();
        $this->accepted_mx_host = $attempt->getAcceptedMxHost();
        $this->smtp_conversations = $attempt->getSmtpConversations();
        $this->error = $attempt->getError();
    }

}