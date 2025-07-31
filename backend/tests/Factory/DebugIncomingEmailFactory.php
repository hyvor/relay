<?php

namespace App\Tests\Factory;

use App\Entity\DebugIncomingEmail;
use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<DebugIncomingEmail>
 */
final class DebugIncomingEmailFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return DebugIncomingEmail::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'type' => self::faker()->randomElement(DebugIncomingEmailType::cases()),
            'status' => self::faker()->randomElement(DebugIncomingEmailStatus::cases()),
            'raw_email' => $this->generateRawEmail(),
            'mail_from' => self::faker()->email(),
            'rcpt_to' => self::faker()->email(),
            'parsed_data' => self::faker()->boolean(70) ? $this->generateParsedData() : null,
            'error_message' => self::faker()->boolean(20) ? self::faker()->sentence() : null,
        ];
    }

    public function bounce(): self
    {
        return $this->with([
            'type' => DebugIncomingEmailType::BOUNCE,
            'parsed_data' => $this->generateBounceParsedData(),
        ]);
    }

    public function fbl(): self
    {
        return $this->with([
            'type' => DebugIncomingEmailType::FBL,
            'parsed_data' => $this->generateFblParsedData(),
        ]);
    }

    public function success(): self
    {
        return $this->with([
            'status' => DebugIncomingEmailStatus::SUCCESS,
            'error_message' => null,
        ]);
    }

    public function failed(): self
    {
        return $this->with([
            'status' => DebugIncomingEmailStatus::FAILED,
            'error_message' => self::faker()->sentence(),
        ]);
    }

    private function generateRawEmail(): string
    {
        $from = self::faker()->email();
        $to = self::faker()->email();
        $subject = self::faker()->sentence();
        $body = self::faker()->paragraph();
        $messageId = self::faker()->uuid() . '@' . self::faker()->domainName();
        $date = self::faker()->dateTime()->format('r');

        return "From: {$from}\r\n" .
               "To: {$to}\r\n" .
               "Subject: {$subject}\r\n" .
               "Date: {$date}\r\n" .
               "Message-ID: <{$messageId}>\r\n" .
               "Content-Type: text/plain; charset=utf-8\r\n" .
               "\r\n" .
               $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateParsedData(): array
    {
        return [
            'message_id' => self::faker()->uuid() . '@' . self::faker()->domainName(),
            'subject' => self::faker()->sentence(),
            'from' => self::faker()->email(),
            'to' => self::faker()->email(),
            'timestamp' => self::faker()->dateTime()->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateBounceParsedData(): array
    {
        return [
            'message_id' => self::faker()->uuid() . '@' . self::faker()->domainName(),
            'bounce_type' => self::faker()->randomElement(['hard', 'soft']),
            'bounce_subtype' => self::faker()->randomElement(['general', 'no-email', 'suppressed']),
            'bounced_recipients' => [
                [
                    'email_address' => self::faker()->email(),
                    'status' => '5.1.1',
                    'diagnostic_code' => 'smtp; 550 5.1.1 User unknown',
                ],
            ],
            'timestamp' => self::faker()->dateTime()->format('c'),
            'feedback_id' => self::faker()->uuid(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateFblParsedData(): array
    {
        return [
            'message_id' => self::faker()->uuid() . '@' . self::faker()->domainName(),
            'feedback_type' => self::faker()->randomElement(['abuse', 'fraud', 'virus']),
            'user_agent' => self::faker()->userAgent(),
            'version' => '1.0',
            'original_mail_from' => self::faker()->email(),
            'original_rcpt_to' => self::faker()->email(),
            'arrival_date' => self::faker()->dateTime()->format('c'),
            'source_ip' => self::faker()->ipv4(),
            'authentication_results' => 'spf=pass smtp.mailfrom=' . self::faker()->domainName(),
        ];
    }
}