<?php

namespace TonyGeez\LaravelRouteTracer;

use Illuminate\Support\ServiceProvider;
use TonyGeez\LaravelRouteTracer\Commands\EnableTraceCommand;
use TonyGeez\LaravelRouteTracer\Commands\DisableTraceCommand;
use TonyGeez\LaravelRouteTracer\Commands\ViewTraceCommand;
use TonyGeez\LaravelRouteTracer\Middleware\TraceRouteDependencies;

class TraceRouteDependenciesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/route-tracer.php',
            'route-tracer'
        );

        $this->app->singleton('route-tracer', function ($app) {
            return new TraceRouteDependencies();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/config/route-tracer.php' => config_path('route-tracer.php'),
        ], 'route-tracer-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnableTraceCommand::class,
                DisableTraceCommand::class,
                ViewTraceCommand::class,
            ]);
        }

        // Ensure trace directory exists
        $tracePath = storage_path('logs/traces');
        if (!file_exists($tracePath)) {
            mkdir($tracePath, 0755, true);
        }

        // Register middleware alias
        $this->app['router']->aliasMiddleware('trace-route', TraceRouteDependencies::class);
    }
}