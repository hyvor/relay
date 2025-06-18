<?php

use Hyvor\Internal\Bundle\Security\HyvorAuthenticator;
use Hyvor\Internal\Bundle\Security\UserRole;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Config\SecurityConfig;

return static function (ContainerBuilder $container, SecurityConfig $security): void {

    $security
        ->firewall('hyvor_auth')
        ->stateless(true)
        ->lazy(true)
        ->customAuthenticators([HyvorAuthenticator::class]);

    $security
        ->accessControl()
        ->path('^/api/console')
        ->roles(UserRole::HYVOR_USER);

    # other access control

};
