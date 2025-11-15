<?php

namespace TonyGeez\LaravelRouteTracer\Commands;

use Illuminate\Console\Command;
use TonyGeez\LaravelRouteTracer\Middleware\TraceRouteDependencies;

class EnableTraceCommand extends Command
{
    protected $signature = 'route:trace-enable {routes?* : Route names to trace}';
    protected $description = 'Enable route tracing for specified routes or all routes';

    public function handle(): int
    {
        $routes = $this->argument('routes');

        if (empty($routes)) {
            TraceRouteDependencies::enable();
            $this->info('✔ Route tracing enabled globally for next request');
        } else {
            TraceRouteDependencies::enableForRoutes($routes);
            $this->info('✔ Route tracing enabled for: ' . implode(', ', $routes));
        }

        $this->comment('Traces will be saved to: storage/logs/traces/');

        return self::SUCCESS;
    }
}