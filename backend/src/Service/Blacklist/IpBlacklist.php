<?php

namespace App\Service\Blacklist;

readonly class IpBlacklist implements \JsonSerializable
{

    public function __construct(
        private string $name,
        private string $dnsLookupDomain,
        private string $removalUrl, // if not applicable, use the website URL
    )
    {
    }

    public function getId(): string
    {
        return strtolower($this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDnsLookupDomain(): string
    {
        return $this->dnsLookupDomain;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'dns_lookup_domain' => $this->dnsLookupDomain,
            'removal_url' => $this->removalUrl,
        ];
    }

}