<?php

namespace App\Api\Console\Object;

use App\Entity\ApiKey;

class ApiKeyObject
{
    public int $id;

    public string $name;

    /** @var string[] $scopes */
    public array $scopes;

    public ?string $key;

    public int $created_at;

    public bool $is_enabled;

    public ?int $last_accessed_at;

    public function __construct(ApiKey $apiKey, ?string $rawKey = null)
    {
        $this->id = $apiKey->getId();
        $this->name = $apiKey->getName();
        $this->scopes = $apiKey->getScopes();
        $this->key = $rawKey;
        $this->created_at = $apiKey->getCreatedAt()->getTimestamp();
        $this->is_enabled = $apiKey->getIsEnabled();
        $this->last_accessed_at = $apiKey->getLastAccessedAt()?->getTimestamp();
    }
}
