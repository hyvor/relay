<?php

namespace App\Api\Console\Object;

use App\Entity\ApiKey;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class ApiKeyObject
{
    public int $id;

    public string $name;

    /**
     * @var array<string> $scopes
     */
    #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
    public array $scopes;

    /**
     * @var array<string> $allowed_ips
     */
    #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
    public array $allowed_ips;

    public ?string $key;

    public int $created_at;

    public bool $is_enabled;

    public ?int $last_accessed_at;

    public function __construct(ApiKey $apiKey, ?string $rawKey = null)
    {
        $this->id = $apiKey->getId();
        $this->name = $apiKey->getName();
        $this->scopes = $apiKey->getScopes();
        $this->allowed_ips = $apiKey->getAllowedIps();
        $this->key = $rawKey;
        $this->created_at = $apiKey->getCreatedAt()->getTimestamp();
        $this->is_enabled = $apiKey->getIsEnabled();
        $this->last_accessed_at = $apiKey->getLastAccessedAt()?->getTimestamp();
    }
}
