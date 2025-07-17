<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Api\Console\Resolver\EntityResolver;
use App\Api\Console\Resolver\ProjectResolver;
use App\Service\Management\Health\AllActiveIpsHaveCorrectPtrHealthCheck;
use App\Service\Management\Health\AllQueuesHaveAtLeastOneIpHealthCheck;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // ================ DEFAULTS =================

    // Default configurdevation for services
    $services->defaults()
        ->autowire(true)      // Automatically injects dependencies in your services.
        ->autoconfigure(true); // Automatically registers your services as commands, event subscribers, etc.

    // Makes classes in src/ available to be used as services
    // This creates a service per class whose id is the fully-qualified class name
    $services->load('App\\', '../src/')
        ->exclude([
            '../src/DependencyInjection/',
            '../src/Entity/',
            '../src/Kernel.php',
        ]);

    // ================ CONSOLE API =================
    $services->set(ProjectResolver::class)
        ->tag(
            'controller.argument_value_resolver',
            ['name' => 'console_api_newsletter', 'priority' => 150]
        );
    $services->set(EntityResolver::class)
        ->tag(
            'controller.argument_value_resolver',
            ['name' => 'console_api_resource', 'priority' => 150]
        );
};
