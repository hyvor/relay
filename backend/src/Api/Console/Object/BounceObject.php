<?php

namespace App\Api\Console\Object;

class BounceObject implements \JsonSerializable
{
    public function __construct(
        private string $text,
        private string $status
    ) {
    }

    /**
     * @return array{ text: string, status: string }
     */
    public function jsonSerialize(): array
    {
        return ['text' => $this->text, 'status' => $this->status];
    }
}
