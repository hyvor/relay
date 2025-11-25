<?php

namespace App\Api\Sudo\Object;

use App\Entity\TlsCertificate;
use App\Entity\Type\TlsCertificateStatus;
use App\Entity\Type\TlsCertificateType;

class TlsCertificateObject
{
    public int $id;
    public int $created_at;
    public TlsCertificateType $type;
    public string $domain;
    public TlsCertificateStatus $status;
    public ?int $valid_from;
    public ?int $valid_to;

    public function __construct(TlsCertificate $tlsCertificate)
    {
        $this->id = $tlsCertificate->getId();
        $this->created_at = $tlsCertificate->getCreatedAt()->getTimestamp();
        $this->type = $tlsCertificate->getType();
        $this->domain = $tlsCertificate->getDomain();
        $this->status = $tlsCertificate->getStatus();
        $this->valid_from = $tlsCertificate->getValidFrom()?->getTimestamp();
        $this->valid_to = $tlsCertificate->getValidTo()?->getTimestamp();
    }

}