<?php

namespace App\Service\App\Env;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class AdvisoryProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        /** @var scalar $env */
        $env = $getEnv($name);

        if (is_string($env) && str_starts_with($env, 'postgresql://')) {
            // replace postgresql:// with postgresql+advisory://
            return str_replace('postgresql://', 'postgresql+advisory://', $env);
        }

        return (string) $env;
    }

    public static function getProvidedTypes(): array
    {
        return [
            'advisory' => 'string',
        ];
    }
}
