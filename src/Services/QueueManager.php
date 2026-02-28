<?php

namespace Iquesters\Foundation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
class QueueManager
{
    /**
     * Get all active queues with their metadata
     */
    public function getActiveQueues(): array
    {
        return DB::table('queues')
            ->where('status', 'active')
            ->get()
            ->map(function ($queue) {
                $metas = DB::table('queue_metas')
                    ->where('ref_parent', $queue->id)
                    ->where('status', 'active')
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();
                
                return array_merge((array) $queue, ['metas' => $metas]);
            })
            ->toArray();
    }

    /**
     * Get job count for a specific queue
     */
    public function getQueueJobCount(string $queueName): array
    {
        $total = DB::table('jobs')->where('queue', $queueName)->count();
        
        // Jobs with reserved_at are currently processing
        $processing = DB::table('jobs')
            ->where('queue', $queueName)
            ->whereNotNull('reserved_at')
            ->count();
        
        $waiting = $total - $processing;
        
        return [
            'total' => $total,
            'waiting' => $waiting,
            'processing' => $processing
        ];
    }

    /**
     * Get currently running workers count for a queue
     * Uses reserved_at in jobs table as a reliable indicator
     */
    public function getRunningWorkersCount(string $queueName): int
    {
        // Count jobs that are currently reserved (being processed)
        // Each reserved job = 1 active worker
        return DB::table('jobs')
            ->where('queue', $queueName)
            ->whereNotNull('reserved_at')
            ->count();
    }

    /**
     * Start a worker for a specific queue
     */
    // public function startWorker(string $queueName, array $options = []): bool
    // {
    //     $queue = DB::table('queues')->where('name', $queueName)->first();
        
    //     if (!$queue) {
    //         Log::error("Queue not found: {$queueName}");
    //         return false;
    //     }

    //     $metas = DB::table('queue_metas')
    //         ->where('ref_parent', $queue->id)
    //         ->where('status', 'active')
    //         ->pluck('meta_value', 'meta_key')
    //         ->toArray();

    //     $maxWorkers = (int) ($metas['max_workers'] ?? 1);
    //     $runningWorkers = $this->getRunningWorkersCount($queueName);

    //     if ($runningWorkers >= $maxWorkers) {
    //         Log::info("Max workers already running for queue: {$queueName}");
    //         return false;
    //     }

    //     $timeout = (int) ($metas['timeout'] ?? 120);
    //     $tries = (int) ($metas['max_tries'] ?? 3);
    //     $sleep = (int) ($metas['sleep'] ?? 3);
    //     $memory = (int) ($metas['memory'] ?? 128);

    //     // Use --max-jobs=1 to process only ONE job per worker
    //     // This ensures proper concurrency control
    //     $phpPath = '/usr/local/bin/php'; // Hardcode the verified path

    //     $cmd = sprintf(
    //         '%s %s/artisan queue:work database --queue=%s --timeout=%d --tries=%d --sleep=%d --memory=%d --max-jobs=1 --stop-when-empty',
    //         $phpPath, // Use the absolute path here
    //         base_path(),
    //         $queueName,
    //         $timeout,
    //         $tries,
    //         $sleep,
    //         $memory
    //     );

    //     // Run in background
    //     if (PHP_OS_FAMILY === 'Windows') {
    //         $cmd .= ' > NUL 2>&1';
    //         pclose(popen("start /B " . $cmd, "r"));
    //     } else {
    //         $cmd .= ' > /dev/null 2>&1 &';
    //         exec($cmd);
    //     }

    //     Log::info("Worker started for queue: {$queueName}", [
    //         'command' => $cmd,
    //         'running_workers' => $runningWorkers + 1,
    //         'max_workers' => $maxWorkers
    //     ]);

    //     return true;
    // }
    
public function startWorker(string $queueName, array $options = []): bool
{
    $queue = DB::table('queues')->where('name', $queueName)->first();
    
    if (!$queue) {
        Log::error("Queue not found: {$queueName}");
        return false;
    }

    $metas = DB::table('queue_metas')
        ->where('ref_parent', $queue->id)
        ->where('status', 'active')
        ->pluck('meta_value', 'meta_key')
        ->toArray();

    $maxWorkers = (int) ($metas['max_workers'] ?? 1);
    $runningWorkers = $this->getRunningWorkersCount($queueName);

    // CRITICAL: Check if we're already at max workers
    if ($runningWorkers >= $maxWorkers) {
        Log::debug("Max workers already running for queue: {$queueName}", [
            'running' => $runningWorkers,
            'max' => $maxWorkers
        ]);
        return false;
    }

    $timeout = (int) ($metas['timeout'] ?? 120);
    $tries = (int) ($metas['max_tries'] ?? 3);
    $sleep = (int) ($metas['sleep'] ?? 3);
    $memory = (int) ($metas['memory'] ?? 128);
    $connection = $this->resolveConnection($metas);
    Log::debug("Attempting to start worker", [
        'queue' => $queueName,
        'connection' => $connection,
        'max_workers' => $maxWorkers,
        'running_workers' => $runningWorkers,
        'timeout' => $timeout,
        'tries' => $tries,
        'sleep' => $sleep,
        'memory' => $memory
    ]);
    try {
        $this->forkWorkerProcess($connection, $queueName, $timeout, $tries, $sleep, $memory);

        Log::info("Worker started for queue: {$queueName}", [
            'connection' => $connection,
            'running_workers' => $runningWorkers + 1,
            'max_workers' => $maxWorkers
        ]);

        return true;
    } catch (\Exception $e) {
        Log::error("Failed to start worker", [
            'queue' => $queueName,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Fork a worker process in the background
 */
private function forkWorkerProcess(string $connection, string $queue, int $timeout, int $tries, int $sleep, int $memory): void
{
    $phpBinary = PHP_BINARY;
    $artisan = base_path('artisan');
    
    $command = sprintf(
        '%s %s queue:work %s --queue=%s --timeout=%d --tries=%d --sleep=%d --memory=%d --max-jobs=1 --stop-when-empty',
        escapeshellarg($phpBinary),
        escapeshellarg($artisan),
        escapeshellarg($connection),
        escapeshellarg($queue),
        $timeout,
        $tries,
        $sleep,
        $memory
    );
    
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows - completely detached process
        $windowsCommand = sprintf(
            'start /B "" "%s" "%s" queue:work %s --queue=%s --timeout=%d --tries=%d --sleep=%d --memory=%d --max-jobs=1 --stop-when-empty',
            $phpBinary,
            $artisan,
            $connection,
            $queue,
            $timeout,
            $tries,
            $sleep,
            $memory
        );
        
        pclose(popen($windowsCommand, 'r'));
    } else {
        // Linux - background process with output redirect
        exec($command . ' > /dev/null 2>&1 &');
    }
}

    /**
     * Resolve queue connection from queue metas.
     */
    private function resolveConnection(array $metas): string
    {
        $connection = trim((string) ($metas['connection'] ?? 'database'));

        if ($connection === '') {
            return 'database';
        }

        $availableConnections = array_keys(config('queue.connections', []));

        if (!in_array($connection, $availableConnections, true)) {
            Log::warning('Invalid queue connection configured, falling back to database', [
                'configured_connection' => $connection,
                'fallback' => 'database',
                'available_connections' => $availableConnections,
            ]);

            return 'database';
        }

        return $connection;
    }

    /**
     * Process all queues that have pending jobs
     */
    public function processQueues(): void
    {
        $activeQueues = $this->getActiveQueues();

        foreach ($activeQueues as $queue) {
            $queueName = $queue['name'];
            $jobCounts = $this->getQueueJobCount($queueName);

            // Skip if no jobs waiting
            if ($jobCounts['waiting'] === 0) {
                continue;
            }

            $metas = $queue['metas'];
            $maxWorkers = (int) ($metas['max_workers'] ?? 1);
            $runningWorkers = $this->getRunningWorkersCount($queueName);

            // Calculate how many workers we can start
            // We want to start workers up to max_workers, but only if there are jobs waiting
            $availableSlots = $maxWorkers - $runningWorkers;
            
            if ($availableSlots <= 0) {
                Log::debug("Queue {$queueName} already at max workers", [
                    'running' => $runningWorkers,
                    'max' => $maxWorkers
                ]);
                continue;
            }

            // Start workers for waiting jobs (up to available slots)
            // Each worker will process 1 job (--max-jobs=1)
            $workersToStart = min($availableSlots, $jobCounts['waiting']);

            Log::info("Starting workers for queue: {$queueName}", [
                'waiting_jobs' => $jobCounts['waiting'],
                'running_workers' => $runningWorkers,
                'max_workers' => $maxWorkers,
                'workers_to_start' => $workersToStart
            ]);

            for ($i = 0; $i < $workersToStart; $i++) {
                $this->startWorker($queueName);
                // Small delay to prevent race conditions
                // usleep(100000); // 100ms delay between worker starts
            }
        }
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(): array
    {
        $queues = $this->getActiveQueues();
        $stats = [];

        foreach ($queues as $queue) {
            $queueName = $queue['name'];
            $jobCounts = $this->getQueueJobCount($queueName);
            $runningWorkers = $this->getRunningWorkersCount($queueName);

            $stats[] = [
                'name' => $queueName,
                'status' => $queue['status'],
                'jobs' => $jobCounts,
                'workers' => [
                    'running' => $runningWorkers,
                    'max' => (int) ($queue['metas']['max_workers'] ?? 1)
                ],
                'config' => $queue['metas']
            ];
        }

        return $stats;
    }
}