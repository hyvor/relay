<?php

namespace App\Api\Sudo\Object;

use App\Api\Console\Object\SendObject;
use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\SendFeedback;

class SudoSendObject extends SendObject
{
    public int $project_id;

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
        parent::__construct($send, $attempts, $feedback, $content);
        $this->project_id = $send->getProject()->getId();
    }
}
