<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Config\SecurityConfig;

return static function (ContainerBuilder $container, SecurityConfig $security): void {

    // Allow access to the API for local requests
    // source: https://symfony.com/doc/current/security/access_control.html#matching-access-control-by-ip
    $env = $container->getParameter('kernel.environment');
    if ($env !== 'dev') {
        // local API
        $security
            ->accessControl()
            ->roles(['PUBLIC_ACCESS'])
            ->path('^/api/local')
            ->ips(['127.0.0.1']);
        $security
            ->accessControl()
            ->path('^/api/local')
            ->roles(['ROLE_NO_ACCESS']);
    }

};
