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
     */
    public function getRunningWorkersCount(string $queueName): int
    {
        // This checks running processes (platform dependent)
        if (PHP_OS_FAMILY === 'Windows') {
            $result = Process::run("tasklist /FI \"IMAGENAME eq php.exe\" /FO CSV | findstr \"queue:work.*--queue={$queueName}\"");
        } else {
            $result = Process::run("ps aux | grep 'queue:work.*--queue={$queueName}' | grep -v grep");
        }
        
        $output = $result->output();
        return $output ? substr_count($output, "\n") : 0;
    }

    /**
     * Start a worker for a specific queue
     */
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

        if ($runningWorkers >= $maxWorkers) {
            Log::info("Max workers already running for queue: {$queueName}");
            return false;
        }

        $timeout = (int) ($metas['timeout'] ?? 120);
        $tries = (int) ($metas['max_tries'] ?? 3);
        $sleep = (int) ($metas['sleep'] ?? 3);
        $memory = (int) ($metas['memory'] ?? 128);

        $cmd = sprintf(
            'php %s/artisan queue:work database --queue=%s --timeout=%d --tries=%d --sleep=%d --memory=%d --stop-when-empty',
            base_path(),
            $queueName,
            $timeout,
            $tries,
            $sleep,
            $memory
        );

        // Run in background
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd .= ' > NUL 2>&1';
            pclose(popen("start /B " . $cmd, "r"));
        } else {
            $cmd .= ' > /dev/null 2>&1 &';
            exec($cmd);
        }

        Log::info("Worker started for queue: {$queueName}", [
            'command' => $cmd,
            'running_workers' => $runningWorkers + 1,
            'max_workers' => $maxWorkers
        ]);

        return true;
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

            // Start workers up to max_workers limit
            $workersToStart = min(
                $maxWorkers - $runningWorkers,
                $jobCounts['waiting']
            );

            for ($i = 0; $i < $workersToStart; $i++) {
                $this->startWorker($queueName);
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