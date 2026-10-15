<?php

namespace App\Api\Console\Object;

use App\Entity\Kyc;
use App\Entity\Type\KycAccountType;
use App\Entity\Type\KycStatus;

class KycObject
{
    public int $id;
    public int $created_at;
    public int $updated_at;
    public KycAccountType $account_type;
    public string $name;
    public string $country;
    public string $address;
    public string $website;
    public string $email;
    /**
     * @var string[]
     */
    public array $content_ownership;
    public bool $sending_transactional;
    public bool $sending_distributional;
    public string $use_case;
    public KycStatus $status;
    public ?string $reject_reason;

    public function __construct(Kyc $kyc)
    {
        $this->id = $kyc->getId();
        $this->created_at = $kyc->getCreatedAt()->getTimestamp();
        $this->updated_at = $kyc->getUpdatedAt()->getTimestamp();
        $this->account_type = $kyc->getAccountType();
        $this->name = $kyc->getName();
        $this->country = $kyc->getCountry();
        $this->address = $kyc->getAddress();
        $this->website = $kyc->getWebsite();
        $this->email = $kyc->getEmail();
        $this->content_ownership = $kyc->getContentOwnership();
        $this->sending_transactional = $kyc->isSendingTransactional();
        $this->sending_distributional = $kyc->isSendingDistributional();
        $this->use_case = $kyc->getUseCase();
        $this->status = $kyc->getStatus();
        $this->reject_reason = $kyc->getRejectReason();
    }
}
