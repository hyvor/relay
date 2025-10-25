<?php

namespace App\Tests\Service\Domain;

use App\Service\Domain\Dkim;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dkim::class)]
class DkimTest extends TestCase
{

    public function test_generate_dkim_functions(): void
    {
        $selector = Dkim::generateDkimSelector();
        $this->assertStringStartsWith('rly', $selector);
        $this->assertSame(25, strlen($selector));

        $host = Dkim::dkimHost('mysel', 'example.com');
        $this->assertSame('mysel._domainkey.example.com', $host);

        $publicKey = Dkim::dkimTxtValue("-----BEGIN PUBLIC KEY-----\nmypubkey\n-----END PUBLIC KEY-----");
        $this->assertSame('v=DKIM1; k=rsa; p=mypubkey', $publicKey);
    }

    public function test_generate_dkim_keys(): void
    {
        $keys = Dkim::generateDkimKeys();
        $this->assertArrayHasKey('public', $keys);
        $this->assertArrayHasKey('private', $keys);
        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', $keys['public']);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $keys['private']);
    }

}