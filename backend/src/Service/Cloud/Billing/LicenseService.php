<?php

namespace App\Service\Cloud\Billing;

use Hyvor\Internal\Billing\BillingInterface;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicense;
use Psr\Cache\CacheItemPoolInterface;

class LicenseService
{
    public function __construct(
        private BillingInterface $billing,
        private CacheItemPoolInterface $cache,
    ) {}

    public function getCachedLicense(int $organizationId): ResolvedLicense
    {
        $item = $this->cache->getItem($this->getLicenseCacheKey($organizationId));

        $cachedLicense = $item->get();

        if ($cachedLicense instanceof ResolvedLicense) {
            return $cachedLicense;
        }

        $license = $this->billing->license($organizationId);

        $item->set($license);
        $item->expiresAfter(3600);
        $this->cache->save($item);

        return $license;
    }

    public function clearLicenseCache(int $organizationId): void
    {
        $this->cache->deleteItem($this->getLicenseCacheKey($organizationId));
    }

    private function getLicenseCacheKey(int $organizationId): string
    {
        return 'license_' . $organizationId;
    }
}
