<?php

namespace TonyGeez\LaravelRouteTracer\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TraceRouteDependencies
{
    private static bool $enabled = false;
    private static array $snapshot = [];
    private static array $globallyEnabledRoutes = [];

    /**
     * Enable tracing for the next request
     */
    public static function enable(): void
    {
        self::$enabled = true;
        self::$snapshot = get_included_files();
    }

    /**
     * Disable tracing
     */
    public static function disable(): void
    {
        self::$enabled = false;
        self::$snapshot = [];
    }

    /**
     * Enable tracing for specific routes globally
     */
    public static function enableForRoutes(array $routes): void
    {
        self::$globallyEnabledRoutes = array_merge(self::$globallyEnabledRoutes, $routes);
    }

    /**
     * Check if tracing is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        $routeName = $route?->getName();

        // Check if tracing is enabled globally or for this specific route
        $shouldTrace = self::$enabled 
            || in_array($routeName, self::$globallyEnabledRoutes)
            || config('route-tracer.enabled', false);

        if (!$shouldTrace) {
            return $next($request);
        }

        // Capture initial state
        $initialFiles = self::$snapshot ?: get_included_files();
        $initialMemory = memory_get_usage();
        $startTime = microtime(true);

        try {
            $response = $next($request);
            $this->logDependencies($request, $initialFiles, $initialMemory, $startTime);
            return $response;
        } catch (\Throwable $e) {
            $this->logDependencies($request, $initialFiles, $initialMemory, $startTime, $e);
            throw $e;
        }
    }

    private function logDependencies(
        Request $request, 
        array $initialFiles, 
        int $initialMemory, 
        float $startTime,
        ?\Throwable $exception = null
    ): void {
        $finalFiles = get_included_files();
        $newFiles = array_values(array_diff($finalFiles, $initialFiles));
        
        // Filter files based on config
        $newFiles = $this->filterFiles($newFiles);
        
        // Sort for consistent output
        sort($newFiles);

        $route = $request->route();
        $executionTime = (microtime(true) - $startTime) * 1000;
        
        $data = [
            'route' => $route?->getName() ?? 'unnamed',
            'uri' => $request->getRequestUri(),
            'controller' => $route?->getActionName() ?? 'unknown',
            'method' => $request->method(),
            'files_loaded_count' => count($newFiles),
            'files_loaded' => $this->categorizeFiles($newFiles),
            'memory_used_mb' => round((memory_get_usage() - $initialMemory) / 1024 / 1024, 2),
            'execution_time_ms' => round($executionTime, 2),
            'timestamp' => now()->toIso8601String(),
            'exception' => $exception ? [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ] : null,
        ];

        // Save to file
        $this->saveTrace($data);

        // Log summary
        Log::channel(config('route-tracer.log_channel', 'stack'))->debug('Route trace completed', [
            'route' => $data['route'],
            'files_count' => $data['files_loaded_count'],
            'memory_mb' => $data['memory_used_mb'],
            'time_ms' => $data['execution_time_ms'],
        ]);
    }

    private function filterFiles(array $files): array
    {
        $basePath = base_path();
        $excludePatterns = config('route-tracer.exclude_patterns', [
            '/vendor/',
            '/bootstrap/',
            '/storage/',
        ]);

        return array_filter($files, function ($file) use ($basePath, $excludePatterns) {
            // Only include files from the app directory by default
            if (!str_starts_with($file, $basePath)) {
                return false;
            }

            // Exclude patterns
            foreach ($excludePatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    return false;
                }
            }

            return true;
        });
    }

    private function categorizeFiles(array $files): array
    {
        $categorized = [
            'controllers' => [],
            'models' => [],
            'policies' => [],
            'requests' => [],
            'resources' => [],
            'middleware' => [],
            'services' => [],
            'migrations' => [],
            'other' => [],
        ];

        $basePath = base_path();

        foreach ($files as $file) {
            $relativePath = str_replace($basePath . '/', '', $file);

            if (str_contains($file, '/Controllers/')) {
                $categorized['controllers'][] = $relativePath;
            } elseif (str_contains($file, '/Models/')) {
                $categorized['models'][] = $relativePath;
            } elseif (str_contains($file, '/Policies/')) {
                $categorized['policies'][] = $relativePath;
            } elseif (str_contains($file, '/Requests/')) {
                $categorized['requests'][] = $relativePath;
            } elseif (str_contains($file, '/Resources/')) {
                $categorized['resources'][] = $relativePath;
            } elseif (str_contains($file, '/Middleware/')) {
                $categorized['middleware'][] = $relativePath;
            } elseif (str_contains($file, '/Services/')) {
                $categorized['services'][] = $relativePath;
            } elseif (str_contains($file, '/migrations/')) {
                $categorized['migrations'][] = $relativePath;
            } else {
                $categorized['other'][] = $relativePath;
            }
        }

        // Remove empty categories
        return array_filter($categorized, fn($category) => !empty($category));
    }

    private function saveTrace(array $data): void
    {
        $format = config('route-tracer.output_format', 'json');
        $filename = sprintf(
            'route-trace-%s-%s.%s',
            str_replace('.', '-', $data['route']),
            now()->format('Y-m-d-His'),
            $format
        );
        
        $tracePath = storage_path('logs/traces/' . $filename);

        if ($format === 'json') {
            file_put_contents(
                $tracePath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } elseif ($format === 'markdown') {
            file_put_contents($tracePath, $this->formatAsMarkdown($data));
        }
    }

    private function formatAsMarkdown(array $data): string
    {
        $md = "# Route Trace: {$data['route']}\n\n";
        $md .= "**URI:** `{$data['uri']}`\n";
        $md .= "**Method:** `{$data['method']}`\n";
        $md .= "**Controller:** `{$data['controller']}`\n";
        $md .= "**Execution Time:** {$data['execution_time_ms']}ms\n";
        $md .= "**Memory Used:** {$data['memory_used_mb']}MB\n";
        $md .= "**Timestamp:** {$data['timestamp']}\n\n";

        if ($data['exception']) {
            $md .= "## Exception\n\n";
            $md .= "**Message:** {$data['exception']['message']}\n";
            $md .= "**File:** {$data['exception']['file']}:{$data['exception']['line']}\n\n";
        }

        $md .= "## Files Loaded ({$data['files_loaded_count']})\n\n";

        foreach ($data['files_loaded'] as $category => $files) {
            $md .= "### " . ucfirst($category) . " (" . count($files) . ")\n\n";
            foreach ($files as $file) {
                $md .= "- `$file`\n";
            }
            $md .= "\n";
        }

        return $md;
    }
}