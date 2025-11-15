# Laravel Route Tracer

**Runtime execution tracer for Laravel routes** - Captures actual file dependencies and execution flow for any Laravel route.

## Why This Package?

To records the **real** execution path by capturing which files PHP actually loads during a request.

### What You Get

-  **Actual file dependencies** - Not guessing
-  **Execution time** - Real performance metrics
-  **Categorized files** - Controllers, Models, Policies, etc.
-  **Exception tracking** - Traces even when things break
-  **Zero config** - Works out of the box

## Installation

```bash
composer require tonygeez/laravel-route-tracer --dev
```

The package will auto-register via Laravel's package discovery.

Publish Config (Optional)
```bash
php artisan vendor:publish --tag=route-tracer-config
```

## Usage

### Method 1: Middleware (Recommended)
Wrap your routes with the trace middleware:
```php
use TonyGeez\LaravelRouteTracer\Middleware\TraceRouteDependencies;

// Enable for next request
TraceRouteDependencies::enable();

Route::middleware(['trace-route'])->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
    Route::post('/api/users', [UserController::class, 'store']);
});
```

### Method 2: Artisan Commands
```bash
# Enable tracing for next request
php artisan route:trace-enable

# Enable for specific routes
php artisan route:trace-enable api.users.index api.users.store

# Disable tracing
php artisan route:trace-disable

# View traces
php artisan route:trace-view

# View latest trace only
php artisan route:trace-view --latest

# Filter by route name
php artisan route:trace-view --route=api.users
```

### Method 3: Facade
```php
use TonyGeez\LaravelRouteTracer\Facades\RouteTracer;

// Enable tracing
RouteTracer::enable();

// Enable for specific routes
RouteTracer::enableForRoutes(['api.users.index', 'api.orders.show']);

// Check if enabled
if (RouteTracer::isEnabled()) {
    // ...
}

// Disable
RouteTracer::disable();
```

### Method 4: Config (Global)

Set in .env:
```bash
ROUTE_TRACER_ENABLED=true
```

**Example Output**
```
{
    "route": "api.users.index",
    "uri": "/api/v1/users",
    "controller": "App\\Http\\Controllers\\UserController@index",
    "method": "GET",
    "files_loaded_count": 12,
    "files_loaded": {
        "controllers": [
            "app/Http/Controllers/UserController.php"
        ],
        "models": [
            "app/Models/User.php"
        ],
        "policies": [
            "app/Policies/UserPolicy.php"
        ],
        "resources": [
            "app/Http/Resources/UserResource.php"
        ],
        "migrations": [
            "database/migrations/2024_01_01_000000_create_users_table.php"
        ]
    },
    "memory_used_mb": 2.5,
    "execution_time_ms": 45.2,
    "timestamp": "2024-01-15T10:30:45+00:00"
}
```

## Configuration

All options can be configured via `config/route-tracer.php`
```php
return [
    // Enable globally
    'enabled' => env('ROUTE_TRACER_ENABLED', false),
    
    // Output format: 'json' or 'markdown'
    'output_format' => env('ROUTE_TRACER_FORMAT', 'json'),
    
    // Log channel
    'log_channel' => env('ROUTE_TRACER_LOG_CHANNEL', 'stack'),
    
    // Exclude patterns
    'exclude_patterns' => [
        '/vendor/',
        '/bootstrap/',
        '/storage/',
    ],
];
```
