<?php

namespace App\Tests\Factory;

use App\Entity\DnsRecord;
use App\Entity\Type\DnsRecordType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<DnsRecord>
 */
final class DnsRecordFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return DnsRecord::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            "type" => self::faker()->randomElement(DnsRecordType::cases()),
            "subdomain" => self::faker()->domainWord(),
            "content" => self::faker()->ipv4(),
            "ttl" => self::faker()->numberBetween(300, 86400),
            "priority" => self::faker()->numberBetween(0, 100),
            "created_at" => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTime()
            ),
            "updated_at" => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTime()
            ),
        ];
    }

    public function a(): static
    {
        return $this->with([
            'type' => DnsRecordType::A,
            'content' => self::faker()->ipv4(),
            'priority' => 0,
        ]);
    }

    public function aaaa(): static
    {
        return $this->with([
            'type' => DnsRecordType::AAAA,
            'content' => self::faker()->ipv6(),
            'priority' => 0,
        ]);
    }

    public function cname(): static
    {
        return $this->with([
            'type' => DnsRecordType::CNAME,
            'content' => self::faker()->domainName(),
            'priority' => 0,
        ]);
    }

    public function mx(): static
    {
        return $this->with([
            'type' => DnsRecordType::MX,
            'content' => self::faker()->domainName(),
            'priority' => self::faker()->numberBetween(1, 100),
        ]);
    }

    public function txt(): static
    {
        return $this->with([
            'type' => DnsRecordType::TXT,
            'content' => self::faker()->sentence(),
            'priority' => 0,
        ]);
    }
}
