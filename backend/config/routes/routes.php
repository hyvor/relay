<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {

    // sudo API
    $routes->import('../../src/Api/Sudo/Controller', 'attribute')
        ->prefix('/api/sudo')
        ->namePrefix('api_sudo_');

    // console API
    $routes->import('../../src/Api/Console/Controller', 'attribute')
        ->prefix('/api/console')
        ->namePrefix('api_console_');

};