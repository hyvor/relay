<?php

namespace App\Api\Sudo\Object;

use App\Entity\DebugIncomingEmail;
use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;

class DebugIncomingEmailObject
{

    public int $id;
    public int $created_at;
    public DebugIncomingEmailType $type;
    public DebugIncomingEmailStatus $status;
    public string $raw_email;
    public string $mail_from;
    public string $rcpt_to;

    /**
     * @var array<mixed>|null
     */
    public ?array $parsed_data = null;
    public ?string $error_message = null;

    public function __construct(DebugIncomingEmail $email)
    {
        $this->id = $email->getId();
        $this->created_at = $email->getCreatedAt()->getTimestamp();
        $this->type = $email->getType();
        $this->status = $email->getStatus();
        $this->raw_email = $email->getRawEmail();
        $this->mail_from = $email->getMailFrom();
        $this->rcpt_to = $email->getRcptTo();
        $this->parsed_data = $email->getParsedData();
        $this->error_message = $email->getErrorMessage();
    }

}