<?php

namespace App\Service\Domain;

use App\Entity\Domain;

class DkimVerificationService
{

    public function __construct(
        /** @var callable(string, int): mixed */
        private $dnsGetRecord = 'dns_get_record',
    )
    {
    }

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

    private function verifyDkimRecord(
        string $dkimHost,
        string $publicKey,
    ): true|string
    {

        /** @var false|array<array{txt?: string}> $records */
        $records = call_user_func($this->dnsGetRecord, $dkimHost, DNS_TXT);

        if ($records === false) {
            return 'DNS query failed';
        }

        if (count($records) === 0) {
            return 'No TXT records found for DKIM host';
        }

        foreach ($records as $record) {
            if (
                isset($record['txt']) &&
                str_starts_with($record['txt'], 'v=DKIM1;')
            ) {
                $txtValue = $record['txt'];
                $txtValueExpected = Dkim::dkimTxtValue($publicKey);

                if ($txtValue === $txtValueExpected) {
                    return true;
                } else {
                    return 'DKIM public key does not match';
                }
            }
        }

        return 'No valid DKIM record found';

    }

}