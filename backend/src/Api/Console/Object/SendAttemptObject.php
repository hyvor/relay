<?php

namespace App\Api\Console\Object;

use App\Entity\SendAttempt;
use App\Entity\Type\SendAttemptStatus;

class SendAttemptObject
{

    public int $created_at;
    public SendAttemptStatus $status;
    public int $try_count;
    public array $resolved_mx_hosts;
    public ?string $sent_mx_host = null;
    public array $smtp_conversations = [];

    public function __construct(SendAttempt $attempt)
    {
        $this->created_at = $attempt->getCreatedAt()->getTimestamp();
        $this->status = $attempt->getStatus();
        $this->try_count = $attempt->getTryCount();
        $this->resolved_mx_hosts = $attempt->getResolvedMxHosts();
        $this->sent_mx_host = $attempt->getSentMxHost();
        $this->smtp_conversations = $attempt->getSmtpConversations();
    }

}