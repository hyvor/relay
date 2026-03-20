<?php

namespace App\Tests\Service\Dns\Resolve;

use App\Service\Domain\Dkim;
use App\Tests\Case\KernelTestCase;

class IsDkimRecordTest extends KernelTestCase
{
    private function do_test(string $txtRecord, string $publicKey): void
    {
        $this->assertTrue(Dkim::isDkimRecord(
            $txtRecord,
            $matches
        ));

        $this->assertSame($publicKey, $matches[1]);
    }
    public function test_is_dkim_record(): void
    {
        $publicKey = 'test_public_key';

        $this->do_test(
            'v=DKIM1; k=rsa; p=' . $publicKey,
            $publicKey
        );

        $this->do_test(
            'v=DKIM1; k=rsa; p=' . $publicKey . '; s=email',
            $publicKey
        );

        $this->do_test(
            's=email; v=DKIM1; k=rsa; p=' . $publicKey,
            $publicKey
        );

        $this->do_test(
            's=email; v=DKIM1; k=rsa; p=' . $publicKey . '; s=email',
            $publicKey
        );

        $this->do_test(
            'p=' . $publicKey . '; s=email; v=DKIM1; k=rsa;',
            $publicKey
        );
    }

}