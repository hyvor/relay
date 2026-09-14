<?php

namespace App\Api\Sudo\Object;

use App\Entity\Kyc;
use App\Entity\Type\KycBusinessType;
use App\Entity\Type\KycStatus;

class KycObject
{
    public int $id;
    public int $organization_id;
    public int $created_at;
    public int $updated_at;
    public string $full_name;
    public KycBusinessType $business_type;
    public ?string $business_name;
    public string $country;
    public string $address;
    public string $phone;
    public string $website;
    public KycStatus $status;
    public int $submitted_at;

    public function __construct(Kyc $kyc)
    {
        $this->id = $kyc->getId();
        $this->organization_id = $kyc->getOrganizationId();
        $this->created_at = $kyc->getCreatedAt()->getTimestamp();
        $this->updated_at = $kyc->getUpdatedAt()->getTimestamp();
        $this->full_name = $kyc->getFullName();
        $this->business_type = $kyc->getBusinessType();
        $this->business_name = $kyc->getBusinessName();
        $this->country = $kyc->getCountry();
        $this->address = $kyc->getAddress();
        $this->phone = $kyc->getPhone();
        $this->website = $kyc->getWebsite();
        $this->status = $kyc->getStatus();
        $this->submitted_at = $kyc->getSubmittedAt()->getTimestamp();
    }
}
