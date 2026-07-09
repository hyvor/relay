<?php

declare(strict_types=1);

namespace App\Service\Ip\Dto;

readonly class PtrValidationDto
{
    public function __construct(
        public bool $valid,
        public ?string $error = null,
    ) {
    }

}
