<?php

namespace App\Service\Dns\Resolve;


class ResolveResult
{

    public function __construct(
        public int $status,

        /** @var array<ResolveAnswer> */
        public array $answers
    ) {
    }

    public function ok(): bool
    {
        return $this->status === 0;
    }

    public function error(): string
    {
        // @codeCoverageIgnoreStart
        return match ($this->status) {
            0 => 'No error',
            1 => 'Format error',
            2 => 'Server failure',
            3 => 'Non-existent domain (NXDOMAIN)',
            4 => 'Not implemented',
            5 => 'Refused',
            default => 'Unknown error code: ' . $this->status,
        };
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var int $status */
        $status = $data['Status'];

        /** @var array<array{name: string, data: string, type: int, TTL: int}> $answers */
        $answers = $data['Answer'] ?? [];
        $answers = array_map(
            fn(array $answer) => new ResolveAnswer(
                name: $answer['name'],
                data: $answer['data'],
                type: $answer['type'],
                ttl: $answer['TTL']
            ),
            $answers
        );

        return new self($status, $answers);
    }

}