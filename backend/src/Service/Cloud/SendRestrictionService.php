<?php

namespace App\Service\Cloud;

use App\Entity\Type\KycStatus;
use App\Service\Cloud\Billing\LicenseService;
use App\Service\Kyc\KycService;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;

class SendRestrictionService
{
    public function __construct(
        private KycService $kycService,
        private LicenseService $licenseService,
    ) {}

    public function isRestricted(int $organizationId): bool
    {
        $kyc = $this->kycService->getActiveByOrganizationId($organizationId);
        if ($kyc?->getStatus() !== KycStatus::APPROVED) {
            return true;
        }

        $licenseType = $this->licenseService->getCachedLicense($organizationId)->type;
        return $licenseType !== ResolvedLicenseType::SUBSCRIPTION &&
            $licenseType !== ResolvedLicenseType::ENTERPRISE_CONTRACT;
    }
}
