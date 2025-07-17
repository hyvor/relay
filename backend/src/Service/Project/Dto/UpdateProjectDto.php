<?php

namespace App\Service\Project\Dto;

use App\Util\OptionalPropertyTrait;

class UpdateProjectDto
{
    use OptionalPropertyTrait;

    public string $name;
} 