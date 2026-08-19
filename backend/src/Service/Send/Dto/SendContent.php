<?php

namespace App\Service\Send\Dto;

class SendContent
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $raw,
        public readonly ?string $bodyHtml,
        public readonly ?string $bodyText,
        public readonly array $headers,
    ) {
    }
}
