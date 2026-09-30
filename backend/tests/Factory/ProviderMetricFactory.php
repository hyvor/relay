<?php

namespace App\Tests\Factory;

use App\Entity\ProviderMetric;
use App\Entity\Type\ProviderMetricSource;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProviderMetric>
 */
final class ProviderMetricFactory extends PersistentObjectFactory
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return ProviderMetric::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'created_at' => new \DateTimeImmutable(),
            'source' => ProviderMetricSource::GOOGLE,
            'metric_date' => new \DateTimeImmutable('yesterday'),
            'value' => (string) self::faker()->randomFloat(4, 0, 0.01),
        ];
    }
}
