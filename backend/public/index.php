<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $env = $context['APP_ENV'];
    return new Kernel(is_string($env) ? $env : 'dev', (bool) $context['APP_DEBUG']);
};
