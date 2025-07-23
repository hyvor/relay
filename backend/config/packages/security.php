<?php

use Hyvor\Internal\Bundle\Security\HyvorAuthenticator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Config\SecurityConfig;

return static function (ContainerBuilder $container, SecurityConfig $security): void {

    $security
        ->firewall('hyvor_auth')
        ->stateless(true)
        ->lazy(true)
        ->customAuthenticators([HyvorAuthenticator::class]);

    /*$security
        ->accessControl()
        ->path('^/api/console')
        ->roles(UserRole::HYVOR_USER);*/

    // Allow access to the API for local requests
    // source: https://symfony.com/doc/current/security/access_control.html#matching-access-control-by-ip
    /*$env = $container->getParameter('kernel.environment');
    if ($env !== 'dev') {
        $security
            ->accessControl()
            ->roles(['PUBLIC_ACCESS'])
            ->path('^/api/local')
            ->ips(['127.0.0.1']);
        $security
            ->accessControl()
            ->path('^/api/local')
            ->roles(['ROLE_NO_ACCESS']);
    }*/

    # other access control

};
