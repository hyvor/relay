<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {

    // admin API
    $routes->import('../../src/Api/Admin/Controller', 'attribute')
        ->prefix('/api/admin')
        ->namePrefix('api_admin_');

    // console API
    $routes->import('../../src/Api/Console/Controller', 'attribute')
        ->prefix('/api/console')
        ->namePrefix('api_console_');

};