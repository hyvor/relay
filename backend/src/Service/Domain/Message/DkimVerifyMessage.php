<?php

namespace App\Service\Domain\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('sync')]
readonly class DkimVerifyMessage
{

    public function __construct(
        public int $domainId,
    )
    {
    }

}