<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Api\Console\Resolver\EntityResolver;
use App\Api\Console\Resolver\ProjectResolver;
use App\Service\Dns\Resolve\DnsOverHttp;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\SelfHosted\RelayTelemetryProvider;
use App\Service\Storage\FilesystemFactory;
use AsyncAws\S3\S3Client;
use Hyvor\Internal\Bundle\EventDispatcher\TestEventDispatcher;
use League\Flysystem\Filesystem;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APCng;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('env(HOSTING)', 'self') // Default to self-hosted
    ;
    $services = $containerConfigurator->services();

    // ================ DEFAULTS =================

    // Default configurdevation for services
    $services
        ->defaults()
        ->autowire(true)      // Automatically injects dependencies in your services.
        ->autoconfigure(true); // Automatically registers your services as commands, event subscribers, etc.

    // Makes classes in src/ available to be used as services
    // This creates a service per class whose id is the fully-qualified class name
    $services
        ->load('App\\', '../src/')
        ->exclude([
            '../src/DependencyInjection/',
            '../src/Entity/',
            '../src/Kernel.php',
        ]);

    // ================ CONSOLE API =================
    $services
        ->set(ProjectResolver::class)
        ->tag(
            'controller.argument_value_resolver',
            ['name' => 'console_api_newsletter', 'priority' => 150],
        );
    $services
        ->set(EntityResolver::class)
        ->tag(
            'controller.argument_value_resolver',
            ['name' => 'console_api_resource', 'priority' => 150],
        );

    // ================ OTHER SERVICES =================
    $services->alias(DnsResolveInterface::class, DnsOverHttp::class);

    // Lock store shares Doctrine's managed `default` connection (instead of opening its
    // own, unmanaged one) so it benefits from doctrine.dbal's idle_connection_ttl recycling.
    // We use a PostgreSQL advisory lock store.
    $services
        ->set(DoctrineDbalPostgreSqlStore::class)
        ->arg('$connOrUrl', service('doctrine.dbal.default_connection'));
    $services
        ->set(LockFactory::class)
        ->arg('$store', service(DoctrineDbalPostgreSqlStore::class));

    // see hyvor/internal
    $services
        ->set(PdoSessionHandler::class)
        ->args([
            env('DATABASE_URL'),
            ['db_table' => 'oidc_sessions'],
        ]);

    // metrics
    $services->set(APCng::class);
    $services->alias(Adapter::class, APCng::class);

    // ================ STORAGE =================
    $services->set(S3Client::class)
        ->lazy()
        ->args([
            '$configuration' => [
                'endpoint' => '%env(default::string:S3_ENDPOINT)%',
                'accessKeyId' => '%env(default::string:S3_KEY)%',
                'accessKeySecret' => '%env(default::string:S3_SECRET)%',
                'region' => '%env(default::string:S3_REGION)%',
                'pathStyleEndpoint' => true,
            ],
        ]);

    $services->set(Filesystem::class)
        ->factory([FilesystemFactory::class, 'create'])
        ->args([
            '%env(string:FILESYSTEM)%',
            new Reference(S3Client::class),
            '%env(default::string:S3_BUCKET)%',
        ]);

    // RelayTelemetryProvider is intentionally not bound to TelemetryProviderInterface,
    // but kept as a public service so its test can fetch it. The class is preserved
    // for upcoming Enterprise license logic.
    $services->set(RelayTelemetryProvider::class)->public();

    // ================ MIGRATIONS =================
    $services->load('DoctrineMigrations\\', '../migrations/');

    $services->set(\DoctrineMigrations\Version20260703120538::class)
        ->tag('doctrine_migrations.migration')
        ->args([
            new Reference('doctrine.migrations.connection'),
            new Reference('doctrine.migrations.logger'),
            new Reference(Filesystem::class),
        ]);
};

