<?php

namespace App\Tests\Service\Smtp;

use App\Entity\SendAttemptRecipient;
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
    #[TestWith([500, null, false])]
    // recipient bounces
    #[TestWith([550, '5.1.1', true])]
    #[TestWith([550, '5.1.1', true])]
    #[TestWith([550, '5.1.3', true])]
    #[TestWith([550, '5.5.0', true])]
    #[TestWith([550, '5.7.1', false])]
    #[TestWith([null, '5.1.1', true])]
    #[TestWith([null, '4.2.0', false])]
    #[TestWith([null, null, false])]
    public function test_is_recipient_bounce(
        ?int $code,
        ?string $enhancedCode,
        bool $result
    ): void {
        $parser = new SmtpResponseParser($code, $enhancedCode, 'Some message');
        $this->assertSame($result, $parser->isRecipientBounce());
    }

    #[TestWith([null, false])]
    #[TestWith(['4.2.0', false])]
    #[TestWith(['5.1.0', false])]
    #[TestWith(['5.7.1', true])]
    #[TestWith(['5.7.2', true])]
    #[TestWith(['4.7.1', true])]
    public function test_is_infra_error(?string $enhancedCode, bool $result): void
    {
        $parser = new SmtpResponseParser(550, $enhancedCode, 'Some message');
        $this->assertSame($result, $parser->isInfrastructureError());
    }

    #[TestWith([250, null, 'OK', '250 OK'])]
    #[TestWith([550, null, 'Mailbox not found', '550 Mailbox not found'])]
    #[TestWith([550, '5.1.1', 'Mailbox not found', '550 5.1.1 Mailbox not found'])]
    public function test_full_message(
        ?int $code,
        ?string $enhancedCode,
        string $message,
        string $expectedFullMessage
    ): void {
        $parser = new SmtpResponseParser($code, $enhancedCode, $message);
        $this->assertSame($expectedFullMessage, $parser->getFullMessage());
    }

    public function test_from_attempt_recipient(): void
    {
        $recipient = new SendAttemptRecipient();
        $recipient->setSmtpCode(550);
        $recipient->setSmtpEnhancedCode('5.1.1');
        $recipient->setSmtpMessage('Mailbox not found');

        $parser = SmtpResponseParser::fromAttemptRecipient($recipient);
        $this->assertSame('550 5.1.1 Mailbox not found', $parser->getFullMessage());
    }

}