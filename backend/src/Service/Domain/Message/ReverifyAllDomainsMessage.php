<?php

namespace App\Service\Domain\Message;

readonly class ReverifyAllDomainsMessage
{

    public function __construct(private int $batchSize = 40)
    {
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

}