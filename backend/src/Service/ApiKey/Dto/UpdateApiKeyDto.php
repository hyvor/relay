<?php

namespace App\Service\ApiKey\Dto;

use App\Util\OptionalPropertyTrait;

class UpdateApiKeyDto
{
    use OptionalPropertyTrait;

    public bool $enabled;

    public string $name;

    /**
     * @var string[] $scopes
     */
    public array $scopes;

    /**
     * @var string[] $allowedIps
     */
    public array $allowedIps;

    public \DateTimeImmutable $lastAccessedAt;
}
