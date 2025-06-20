<?php

namespace App\Service\ApiKey\Dto;

use App\Util\OptionalPropertyTrait;

class UpdateApiKeyDto
{
    use OptionalPropertyTrait;

    public bool $enabled;
}