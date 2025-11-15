<?php

namespace TonyGeez\LaravelRouteTracer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ViewTraceCommand extends Command
{
    protected $signature = 'route:trace-view {--route= : Filter by route name} {--latest : Show only the latest trace}';
    protected $description = 'View route trace files';

    public function handle(): int
    {
        $tracePath = storage_path('logs/traces');
        
        if (!File::exists($tracePath)) {
            $this->error('No traces found. Run route:trace-enable first.');
            return self::FAILURE;
        }

        $files = File::files($tracePath);
        
        if (empty($files)) {
            $this->error('No trace files found.');
            return self::FAILURE;
        }

        // Sort by modification time (newest first)
        usort($files, fn($a, $b) => $b->getMTime() <=> $a->getMTime());

        // Filter by route name if specified
        $routeFilter = $this->option('route');
        if ($routeFilter) {
            $files = array_filter($files, fn($file) => str_contains($file->getFilename(), $routeFilter));
        }

        // Show only latest if specified
        if ($this->option('latest')) {
            $files = array_slice($files, 0, 1);
        }

        if (empty($files)) {
            $this->error('No matching trace files found.');
            return self::FAILURE;
        }

        foreach ($files as $file) {
            $this->displayTrace($file);
        }

        return self::SUCCESS;
    }

    private function displayTrace($file): void
    {
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════');
        $this->info('📄 ' . $file->getFilename());
        $this->line('═══════════════════════════════════════════════════════════');

        $content = File::get($file->getPathname());
        $data = json_decode($content, true);

        if (!$data) {
            $this->error('Failed to parse trace file');
            return;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Route', $data['route']],
                ['URI', $data['uri']],
                ['Method', $data['method']],
                ['Controller', $data['controller']],
                ['Files Loaded', $data['files_loaded_count']],
                ['Memory Used', $data['memory_used_mb'] . ' MB'],
                ['Execution Time', $data['execution_time_ms'] . ' ms'],
                ['Timestamp', $data['timestamp']],
            ]
        );

        if (isset($data['exception'])) {
            $this->error('Exception: ' . $data['exception']['message']);
        }

        if ($this->confirm('Show file list?', false)) {
            foreach ($data['files_loaded'] as $category => $files) {
                $this->newLine();
                $this->comment(ucfirst($category) . ' (' . count($files) . '):');
                foreach ($files as $filePath) {
                    $this->line('  • ' . $filePath);
                }
            }
        }
    }
}