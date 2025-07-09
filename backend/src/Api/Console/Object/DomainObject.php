<?php

namespace App\Api\Console\Object;

use App\Entity\Domain;
use App\Service\Domain\Dkim;

class DomainObject
{

    public int $id;
    public int $created_at;
    public string $domain;
    public string $dkim_selector;
    public string $dkim_host;
    public string $dkim_txt_name;
    public string $dkim_public_key;
    public string $dkim_txt_value;
    public bool $dkim_verified;
    public ?int $dkim_checked_at;
    public ?string $dkim_error_message;

    public function __construct(Domain $domain)
    {
        $this->id = $domain->getId();
        $this->created_at = $domain->getCreatedAt()->getTimestamp();
        $this->domain = $domain->getDomain();
        $this->dkim_selector = $domain->getDkimSelector();
        $this->dkim_host = Dkim::dkimHost($domain->getDkimSelector(), $domain->getDomain());
        $this->dkim_txt_name = $this->dkim_host;
        $this->dkim_public_key = $domain->getDkimPublicKey();
        $this->dkim_txt_value = Dkim::dkimTxtValue($domain->getDkimPublicKey());
        $this->dkim_verified = $domain->getDkimVerified();
        $this->dkim_checked_at = $domain->getDkimCheckedAt()?->getTimestamp();
        $this->dkim_error_message = $domain->getDkimErrorMessage();
    }

}