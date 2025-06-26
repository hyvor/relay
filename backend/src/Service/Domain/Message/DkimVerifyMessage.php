<?php

namespace App\Service\Domain\Message;

class DkimVerifyMessage
{

    public function __construct(
        public int $domainId,
    )
    {
    }

}