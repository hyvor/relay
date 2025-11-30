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
        // Separate handler for streamer logs (to avoid buffering delays in long-running commands)
        $monolog->handler('streamer')
            ->type('stream')
            ->path('php://stderr')
            ->level("%env(LOG_LEVEL)%")
            ->formatter('monolog.formatter.line')
            ->channels()->elements(['streamer']);
        $monolog->channels(['streamer']);
    } else {
        $monolog->handler('test')
            ->type('test')
            ->level('info');
    }
};