<?php

namespace App\Api\Console\Object;

use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\SendFeedback;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class SendObject
{
    public int $id;
    public string $uuid;
    public int $created_at;
    public string $from_address;
    public ?string $from_name;
    public ?string $subject;
    public int $size_bytes;
    public bool $queued;
    public int $send_after;

    /**
     * @var list<SendRecipientObject>
     */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: SendRecipientObject::class)))]
    public array $recipients = [];

    /**
     * @var list<SendAttemptObject>
     */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: SendAttemptObject::class)))]
    public array $attempts = [];

    /**
     * @var list<SendFeedbackObject>
     */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: SendFeedbackObject::class)))]
    public array $feedback = [];

    /**
     * @param SendAttempt[] $attempts
     * @param SendFeedback[] $feedback
     */
    public function __construct(
        Send $send,
        array $attempts = [],
        array $feedback = [],
    ) {
        $this->id = $send->getId();
        $this->uuid = $send->getUuid();
        $this->created_at = $send->getCreatedAt()->getTimestamp();
        $this->from_address = $send->getFromAddress();
        $this->from_name = $send->getFromName();
        $this->subject = $send->getSubject();
        $this->size_bytes = $send->getSizeBytes();
        $this->queued = $send->getQueued();
        $this->send_after = $send->getSendAfter()->getTimestamp();

        $this->recipients = array_values(array_map(
            fn($recipient) => new SendRecipientObject($recipient),
            $send->getRecipients()->toArray()
        ));
        $this->attempts = array_values(array_map(
            fn(SendAttempt $attempt) => new SendAttemptObject($attempt),
            $attempts
        ));
        $this->feedback = array_values(array_map(
            fn(SendFeedback $fb) => new SendFeedbackObject($fb),
            $feedback
        ));
    }
}
