<?php

namespace App\Service\Domain;

use App\Entity\Domain;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Domain\Exception\DkimVerificationFailedException;

class DkimVerificationService
{

    public function __construct(
        private DnsResolveInterface $dnsResolve,
    ) {
    }

    /**
     * @throws DkimVerificationFailedException
     */
    public function verify(Domain $domain): DkimVerificationResult
    {
        $startTime = new \DateTimeImmutable();

        $domainName = $domain->getDomain();
        $selector = $domain->getDkimSelector();
        $publicKey = $domain->getDkimPublicKey();

        $dkimHost = Dkim::dkimHost($selector, $domainName);

        $result = new DkimVerificationResult();
        $result->checkedAt = $startTime;

        $verifyResult = $this->verifyDkimRecord($dkimHost, $publicKey);

        if ($verifyResult === true) {
            $result->verified = true;
        } else {
            $result->verified = false;
            $result->errorMessage = $verifyResult;
        }

        return $result;
    }

    /**
     * @throws DkimVerificationFailedException
     */
    private function verifyDkimRecord(
        string $dkimHost,
        string $publicKey,
    ): true|string {
        try {
            $result = $this->dnsResolve->resolve($dkimHost, DnsType::TXT);
        } catch (DnsResolvingFailedException $e) {
            throw new DkimVerificationFailedException('DNS Resolving failed: ' . $e->getMessage(), previous: $e);
        }

        if (!$result->ok()) {
            return 'DNS query failed with error: ' . $result->error();
        }

        if (count($result->answers) === 0) {
            return 'No TXT records found for DKIM host';
        }

        foreach ($result->answers as $answer) {

            if (preg_match('/(?:^|;\s*)p=([^;]+)/i', $answer->getCleanedTxt(), $matches)) {

                $dnsKey = trim($matches[1]);
                $expectedKey = Dkim::cleanKey($publicKey);

                if ($dnsKey === $expectedKey) {
                    return true;
                }
            }
        }

        return 'No valid DKIM record found';
    }

}