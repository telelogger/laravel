<?php

namespace Telelogger;

use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

/**
 * Class ServiceProvider
 *
 * @package Telelogger
 */
class ServiceProvider extends IlluminateServiceProvider
{
    public static $package = 'telelogger';
    
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->app->make(static::$package);

        $this->bindEvents();
        
        $this->publishes([
            __DIR__ . '/config/telelogger.php' => config_path(static::$package . '.php'),
        ], 'config');
    }
    
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        // Объединяем конфиг
        $this->mergeConfigFrom(__DIR__ . '/config/telelogger.php', self::$package);
        // Если существует лог менеджер
        if (($logManager = $this->app->make('log')) instanceof LogManager) {
            // Регистрируем свой драйвер
            $logManager->extend('telelogger', function ($app, array $config) {
                return (new LogChannel($app))($config);
            });
        }
    }
    
    /**
     * Bind to the Laravel event dispatcher to log events.
     */
    protected function bindEvents()
    {
        $handler = new EventHandler($this->app->events);
        
        $handler->subscribe();
        
        $handler->subscribeQueueEvents();
    
        $handler->subscribeAuthEvents();
    }
    
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [static::$package];
    }
}
