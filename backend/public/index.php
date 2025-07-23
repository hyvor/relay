<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    // @phpstan-ignore-next-line
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
