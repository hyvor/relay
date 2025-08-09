<?php

use Symfony\Config\MonologConfig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (MonologConfig $monolog, ContainerConfigurator $container): void {
    if ($container->env() !== 'test') {
        $monolog->handler('app')
            ->type('buffer')
            ->handler('final')
            ->level("%env(LOG_LEVEL)%")
            ->bubble(false)
            ->channels()->elements(['app']);
        $monolog->handler('non_app')
            ->type('buffer')
            ->handler('final')
            ->level('error')
            ->bubble(false)
            ->channels()->elements(['!app']);
        $monolog->handler('final')
            ->type('stream')
            ->path('php://stderr')
            ->formatter('monolog.formatter.json');
    } else {
        $monolog->handler('test')
            ->type('test')
            ->level('info');
    }
};