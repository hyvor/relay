<?php

namespace App\Service\Domain\Event;

use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\DkimVerificationResult;

readonly class DomainStatusChangedEvent
{

    public function __construct(
        public Domain $domain,
        public DomainStatus $oldStatus,
        public DomainStatus $newStatus,
        public ?DkimVerificationResult $result = null,
    ) {
        assert($this->oldStatus !== $this->newStatus, 'Domain status should be changed.');
    }

}