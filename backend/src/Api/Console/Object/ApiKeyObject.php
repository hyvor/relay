<?php

namespace App\Api\Console\Object;

use App\Entity\ApiKey;

class ApiKeyObject
{
    public string $name;

    public string $scope;

    public string $key;

    public string $created_at;
    public bool $is_enabled;

    public function __construct(ApiKey $apiKey)
    {
        $this->name = $apiKey->getName();
        $this->scope = $apiKey->getScope()->value;
        $this->key = $apiKey->getKey();
        $this->created_at = $apiKey->getCreatedAt()->getTimestamp();
        $this->is_enabled = $apiKey->getIsEnabled();
    }
}