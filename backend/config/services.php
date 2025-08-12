<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Api\Console\Resolver\EntityResolver;
use App\Api\Console\Resolver\ProjectResolver;
use App\Service\Dns\Resolve\DnsOverHttp;
use App\Service\Dns\Resolve\DnsResolveInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('env(HOSTING)', 'self') // Default to self-hosted
    ;

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

    // ================ OTHER SERVICES =================
    $services->alias(DnsResolveInterface::class, DnsOverHttp::class);

    $services->set(PdoSessionHandler::class)
        ->args([
            env('DATABASE_URL'),
            ['db_table' => 'oidc_sessions'],
        ]);
};
