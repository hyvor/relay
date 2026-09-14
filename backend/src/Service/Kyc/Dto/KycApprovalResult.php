<?php

namespace App\Service\Kyc\Dto;

use App\Entity\Kyc;

readonly class KycApprovalResult
{

    public function __construct(
        public Kyc $kyc,
        public bool $chargeSuccess,
        public ?string $chargeError,
    ) {
    }

}
