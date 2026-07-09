<?php

declare(strict_types=1);

namespace App\Service\Webhook\Dto;

use App\Util\OptionalPropertyTrait;

class UpdateWebhookDto
{
    use OptionalPropertyTrait;

    public string $url;

    public string $description;

    /**
     * @var string[]
     */
    public array $events;
}
