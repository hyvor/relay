<?php

namespace App\Api\Console\RateLimit;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RateLimitConfig array{id: string, policy: string, limit: int, interval: string}
 */
class RateLimit
{

    private bool $isDev;

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $env = 'prod'
    )
    {
        $this->isDev = $this->env === 'dev';
    }

    /**
     * Rate limit for a user session.
     * 60 per minute
     * @return RateLimitConfig
     */
    public function session(): array
    {
        return [
            'id' => 'console_api_session',
            'policy' => 'fixed_window',
            'limit' => $this->isDev ? 1000 : 60,
            'interval' => '1 minute',
        ];
    }

    /**
     * Rate limit for an API key.
     * 100 per minute
     * @return RateLimitConfig
     */
    public function apiKey(): array
    {
        return [
            'id' => 'console_api_api_key',
            'policy' => 'fixed_window',
            'limit' => $this->isDev ? 1000 : 100,
            'interval' => '1 minute',
        ];
    }

}