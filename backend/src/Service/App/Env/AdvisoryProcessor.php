<?php

namespace App\Service\App\Env;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class AdvisoryProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $env = $getEnv($name);
        // replace postgresql:// with postgresql+advisory://
        return str_replace('postgresql://', 'postgresql+advisory://', $env);
    }

    public static function getProvidedTypes(): array
    {
        return [
            'advisory' => 'string',
        ];
    }
}
