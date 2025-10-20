<?php

namespace App\Tests\Service\Smtp;

use App\Service\Smtp\SmtpResponseParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmtpResponseParser::class)]
class SmtpResponseParserTest extends TestCase
{

    // not a bounce
    #[TestWith([250, null, false])]
    #[TestWith([450, '4.2.0', false])]
    // recipient bounces
    #[TestWith([550, '5.1.1', true])]
    #[TestWith([550, '5.1.1', true])]
    public function test_is_recipient_bounce(
        int $code,
        ?string $enhancedCode,
        bool $result
    ): void {
        $parser = new SmtpResponseParser($code, $enhancedCode, 'Some message');
        $this->assertSame($result, $parser->isRecipientBounce());
    }

}