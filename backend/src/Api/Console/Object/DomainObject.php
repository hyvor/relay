<?php

namespace App\Api\Console\Object;

use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\Dkim;

class DomainObject
{

    public int $id;
    public int $created_at;
    public string $domain;
    public DomainStatus $status;
    public int $status_changed_at;
    public string $dkim_selector;
    public string $dkim_host;
    public string $dkim_public_key;
    public string $dkim_txt_value;
    public ?int $dkim_checked_at;
    public ?string $dkim_error_message;

    public function __construct(Domain $domainEntity)
    {
        $this->id = $domainEntity->getId();
        $this->created_at = $domainEntity->getCreatedAt()->getTimestamp();
        $this->domain = $domainEntity->getDomain();
        $this->status = $domainEntity->getStatus();
        $this->status_changed_at = $domainEntity->getStatusChangedAt()->getTimestamp();

        $this->dkim_selector = $domainEntity->getDkimSelector();
        $this->dkim_host = Dkim::dkimHost($domainEntity->getDkimSelector(), $domainEntity->getDomain());
        $this->dkim_public_key = $domainEntity->getDkimPublicKey();
        $this->dkim_txt_value = Dkim::dkimTxtValue($domainEntity->getDkimPublicKey());
        $this->dkim_checked_at = $domainEntity->getDkimCheckedAt()?->getTimestamp();
        $this->dkim_error_message = $domainEntity->getDkimErrorMessage();
    }

}
