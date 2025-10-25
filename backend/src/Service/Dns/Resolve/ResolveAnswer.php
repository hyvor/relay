<?php

namespace App\Service\Dns\Resolve;

class ResolveAnswer
{

    public function __construct(
        public string $name,
        public string $data,
        public int $type = 0,
        public int $ttl = 0,
    ) {
    }

    public function getCleanedTxt(): string
    {
        $data = trim($this->data, '"');
        $parts = preg_split('/"\s+"/', $data);
        if ($parts === false) {
            return $data; // @codeCoverageIgnore
        }
        $cleanedParts = array_map(fn($part) => trim($part, '"'), $parts);
        return implode('', $cleanedParts);
    }

}