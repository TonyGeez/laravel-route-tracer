<?php

namespace TonyGeez\LaravelRouteTracer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void enable()
 * @method static void disable()
 * @method static void enableForRoutes(array $routes)
 * @method static bool isEnabled()
 */
class RouteTracer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'route-tracer';
    }
}