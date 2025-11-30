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

    // local API
    $routes->import('../../src/Api/Local/Controller', 'attribute')
        ->prefix('/api/local')
        ->namePrefix('api_local_');

    // OIDC routes
    $routes->import('@InternalBundle/src/Controller/OidcController.php', 'attribute')
        ->prefix('/api/oidc')
        ->namePrefix('api_oidc_');

    // root API
    $routes->import('../../src/Api/Root', 'attribute')
        ->prefix('/api')
        ->namePrefix('api_root_');
};