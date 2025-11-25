<?php

namespace App\Service\Domain\Message;

use App\Entity\Type\DomainStatus;
use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class ReverifyDomainsMessage
{

    public function __construct(
        /**
         * @var DomainStatus[] $statuses
         */
        private array $statuses,
        private int $batchSize = 40
    ) {
    }

    /**
     * @return DomainStatus[]
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * @return string[]
     */
    public function getStatusNames(): array
    {
        return array_map(fn(DomainStatus $status) => $status->value, $this->statuses);
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

}