<?php

namespace TonyGeez\LaravelRouteTracer\Commands;

use Illuminate\Console\Command;
use TonyGeez\LaravelRouteTracer\Middleware\TraceRouteDependencies;

class DisableTraceCommand extends Command
{
    protected $signature = 'route:trace-disable';
    protected $description = 'Disable route tracing';

    public function handle(): int
    {
        TraceRouteDependencies::disable();
        $this->info('✔ Route tracing disabled');

        return self::SUCCESS;
    }
}