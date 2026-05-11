<?php

namespace App\Api\Sudo\Object;

use App\Entity\Send;

class SendObject
{
    public int $id;
    public string $uuid;
    public int $created_at;
    public string $from_address;
    public ?string $from_name;
    public ?string $subject;
    public bool $queued;

    /**
     * @var SudoSendRecipientObject[]
     */
    public array $recipients = [];

    /**
     * @var array{id: int, name: string}
     */
    public array $project;

    public function __construct(Send $send)
    {
        $this->id = $send->getId();
        $this->uuid = $send->getUuid();
        $this->created_at = $send->getCreatedAt()->getTimestamp();
        $this->from_address = $send->getFromAddress();
        $this->from_name = $send->getFromName();
        $this->subject = $send->getSubject();
        $this->queued = $send->getQueued();

        $this->recipients = array_map(
            fn($recipient) => new SudoSendRecipientObject($recipient),
            $send->getRecipients()->toArray()
        );

        $project = $send->getProject();
        $this->project = [
            'id' => $project->getId(),
            'name' => $project->getName(),
        ];
    }
}
