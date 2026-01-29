<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;
use Iquesters\Foundation\Services\QueueManager;

class QueueManagementController extends Controller
{
    protected $queueManager;

    public function __construct(QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
    }

    /**
     * Display the queue management dashboard
     */
    public function index()
    {
        return view('foundation::queue-management.index');
    }

    /**
     * Get all queues with their current stats
     */
    public function getQueues()
    {
        $stats = $this->queueManager->getQueueStats();
        
        return response()->json([
            'success' => true,
            'data' => $stats,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Get detailed information about a specific queue
     */
    public function getQueueDetails(string $queueName)
    {
        $queue = DB::table('queues')
            ->where('name', $queueName)
            ->where('status', 'active')
            ->first();

        if (!$queue) {
            return response()->json([
                'success' => false,
                'message' => 'Queue not found'
            ], 404);
        }

        $metas = DB::table('queue_metas')
            ->where('ref_parent', $queue->id)
            ->where('status', 'active')
            ->pluck('meta_value', 'meta_key')
            ->toArray();

        $jobCounts = $this->queueManager->getQueueJobCount($queueName);
        $runningWorkers = $this->queueManager->getRunningWorkersCount($queueName);

        // Get recent jobs (last 10)
        $recentJobs = DB::table('jobs')
            ->where('queue', $queueName)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'attempts' => $job->attempts,
                    'reserved_at' => $job->reserved_at,
                    'available_at' => $job->available_at,
                    'created_at' => $job->created_at,
                    'status' => $job->reserved_at ? 'processing' : 'waiting'
                ];
            });

        // Get failed jobs for this queue
        $failedJobs = DB::table('failed_jobs')
            ->where('queue', $queueName)
            ->orderBy('failed_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'queue' => $queue,
                'metas' => $metas,
                'stats' => [
                    'jobs' => $jobCounts,
                    'workers' => [
                        'running' => $runningWorkers,
                        'max' => (int) ($metas['max_workers'] ?? 1)
                    ]
                ],
                'recent_jobs' => $recentJobs,
                'failed_jobs' => $failedJobs
            ]
        ]);
    }

    /**
     * Start workers for a specific queue
     */
    public function startWorkers(Request $request, string $queueName)
    {
        $validated = $request->validate([
            'worker_count' => 'integer|min:1|max:10'
        ]);

        $queue = DB::table('queues')
            ->where('name', $queueName)
            ->where('status', 'active')
            ->first();

        if (!$queue) {
            return response()->json([
                'success' => false,
                'message' => 'Queue not found'
            ], 404);
        }

        $metas = DB::table('queue_metas')
            ->where('ref_parent', $queue->id)
            ->where('status', 'active')
            ->pluck('meta_value', 'meta_key')
            ->toArray();

        $maxWorkers = (int) ($metas['max_workers'] ?? 1);
        $runningWorkers = $this->queueManager->getRunningWorkersCount($queueName);
        $requestedWorkers = $validated['worker_count'] ?? 1;

        // Calculate how many workers we can actually start
        $availableSlots = $maxWorkers - $runningWorkers;
        
        if ($availableSlots <= 0) {
            return response()->json([
                'success' => false,
                'message' => "Queue already has maximum workers running ({$maxWorkers})",
                'data' => [
                    'running_workers' => $runningWorkers,
                    'max_workers' => $maxWorkers
                ]
            ], 400);
        }

        $workersToStart = min($requestedWorkers, $availableSlots);
        $started = 0;

        for ($i = 0; $i < $workersToStart; $i++) {
            if ($this->queueManager->startWorker($queueName)) {
                $started++;
                // Small delay to prevent race conditions
                usleep(100000); // 100ms
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Started {$started} worker(s) for queue: {$queueName}",
            'data' => [
                'started' => $started,
                'requested' => $requestedWorkers,
                'running_workers' => $runningWorkers + $started,
                'max_workers' => $maxWorkers
            ]
        ]);
    }

    /**
     * Start scheduler (for production environments)
     */
    public function startScheduler()
    {
        // Check if scheduler is already running
        if ($this->isSchedulerRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduler is already running'
            ], 400);
        }

        $lockFile = storage_path('framework/schedule-worker.lock');
        
        // Start scheduler
        $cmd = sprintf('php %s/artisan schedule:work', base_path());
        
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd .= ' > NUL 2>&1';
            pclose(popen("start /B " . $cmd, "r"));
        } else {
            $cmd .= ' > /dev/null 2>&1 &';
            exec($cmd . ' & echo $!', $output);
            $pid = !empty($output[0]) ? (int) $output[0] : null;
            
            // Store PID in lock file
            @file_put_contents($lockFile, json_encode([
                'pid' => $pid,
                'started_at' => time()
            ]));
        }

        Log::info('Scheduler started manually from UI');

        return response()->json([
            'success' => true,
            'message' => 'Scheduler started successfully'
        ]);
    }
//     public function startScheduler()
// {
//     if ($this->isSchedulerRunning()) {
//         return response()->json(['success' => false, 'message' => 'Scheduler already running'], 400);
//     }

//     $lockFile = storage_path('framework/schedule-worker.lock');
    
//     // Clean up old lock file if exists
//     if (file_exists($lockFile)) {
//         @unlink($lockFile);
//     }

//     $basePath = base_path();
//     $artisanPath = $basePath . DIRECTORY_SEPARATOR . 'artisan';
    
//     if (PHP_OS_FAMILY === 'Windows') {
//         // Windows: Use START command which works reliably
//         $cmd = sprintf('start /B php "%s" schedule:work', $artisanPath);
        
//         // Execute the command
//         pclose(popen($cmd, 'r'));
        
//         // Give it a moment to start
//         sleep(1);
        
//         // Verify it started
//         if (!$this->isSchedulerRunning()) {
//             return response()->json([
//                 'success' => false, 
//                 'message' => 'Failed to start scheduler - process did not start'
//             ], 500);
//         }
        
//         // Store lock file (we can't easily get PID on Windows with START /B)
//         @file_put_contents($lockFile, json_encode([
//             'pid' => 'windows',
//             'started_at' => time(),
//             'method' => 'start_command'
//         ]));
        
//         Log::info("Scheduler started successfully (Windows)", ['method' => 'START command']);
        
//         return response()->json([
//             'success' => true,
//             'message' => 'Scheduler started successfully'
//         ]);
        
//     } else {
//         // Linux/Mac: Use nohup for reliable background execution
//         $cmd = sprintf('nohup php "%s" schedule:work > /dev/null 2>&1 & echo $!', $artisanPath);
//         $pid = (int) trim(shell_exec($cmd));
        
//         if (!$pid) {
//             return response()->json([
//                 'success' => false, 
//                 'message' => 'Failed to start scheduler - no PID returned'
//             ], 500);
//         }
        
//         // Store PID in lock file
//         @file_put_contents($lockFile, json_encode([
//             'pid' => $pid,
//             'started_at' => time(),
//             'method' => 'nohup'
//         ]));
        
//         Log::info("Scheduler started successfully (Linux/Mac)", ['pid' => $pid]);
        
//         return response()->json([
//             'success' => true,
//             'message' => 'Scheduler started successfully',
//             'pid' => $pid
//         ]);
//     }
// }


    /**
     * Stop scheduler
     */
    public function stopScheduler()
    {
        if (!$this->isSchedulerRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduler is not running'
            ], 400);
        }

        $lockFile = storage_path('framework/schedule-worker.lock');

        if (PHP_OS_FAMILY === 'Windows') {
            exec('taskkill /F /FI "IMAGENAME eq php.exe" /FI "WINDOWTITLE eq *schedule:work*" 2>nul');
            exec('wmic process where "commandline like \'%schedule:work%\'" delete 2>nul');
        } else {
            // Try to read PID from lock file
            if (file_exists($lockFile)) {
                $data = json_decode(file_get_contents($lockFile), true);
                $pid = $data['pid'] ?? null;
                
                if ($pid) {
                    posix_kill($pid, SIGTERM);
                    sleep(1);
                    posix_kill($pid, SIGKILL);
                }
            }
            
            exec("pkill -f 'schedule:work'");
        }

        // Clean up lock file
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }

        Log::info('Scheduler stopped manually from UI');

        return response()->json([
            'success' => true,
            'message' => 'Scheduler stopped successfully'
        ]);
    }

    /**
     * Get scheduler status
     */
    public function getSchedulerStatus()
    {
        $isRunning = $this->isSchedulerRunning();
        $lockFile = storage_path('framework/schedule-worker.lock');
        $startedAt = null;

        if ($isRunning && file_exists($lockFile)) {
            $data = json_decode(file_get_contents($lockFile), true);
            $startedAt = $data['started_at'] ?? null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'running' => $isRunning,
                'started_at' => $startedAt ? date('Y-m-d H:i:s', $startedAt) : null,
                'uptime' => $startedAt ? $this->formatUptime(time() - $startedAt) : null
            ]
        ]);
    }

    /**
     * Check if scheduler is running
     */
    private function isSchedulerRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /NH | findstr /I "php.exe"');
            if (!$output) {
                return false;
            }
            
            $processes = explode("\n", trim($output));
            foreach ($processes as $process) {
                if (stripos($process, 'php.exe') !== false) {
                    $pid = preg_replace('/\s+/', ' ', trim($process));
                    $parts = explode(' ', $pid);
                    if (isset($parts[1])) {
                        $checkCmd = 'wmic process where ProcessId=' . $parts[1] . ' get CommandLine 2>nul';
                        $cmdLine = shell_exec($checkCmd);
                        if (stripos($cmdLine, 'schedule:work') !== false) {
                            return true;
                        }
                    }
                }
            }
            return false;
        } else {
            $output = shell_exec('ps aux | grep "schedule:work" | grep -v grep');
            return !empty($output);
        }
    }

    /**
     * Format uptime in human-readable format
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($secs > 0 || empty($parts)) $parts[] = "{$secs}s";

        return implode(' ', $parts);
    }

    /**
     * Retry a failed job
     */
    public function retryFailedJob(string $jobId)
    {
        $failedJob = DB::table('failed_jobs')->where('uuid', $jobId)->first();

        if (!$failedJob) {
            return response()->json([
                'success' => false,
                'message' => 'Failed job not found'
            ], 404);
        }

        // Re-queue the job
        DB::table('jobs')->insert([
            'queue' => $failedJob->queue,
            'payload' => $failedJob->payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time()
        ]);

        // Delete from failed jobs
        DB::table('failed_jobs')->where('uuid', $jobId)->delete();

        Log::info('Failed job retried', ['job_id' => $jobId, 'queue' => $failedJob->queue]);

        return response()->json([
            'success' => true,
            'message' => 'Job has been re-queued successfully'
        ]);
    }

    /**
     * Delete a failed job
     */
    public function deleteFailedJob(string $jobId)
    {
        $deleted = DB::table('failed_jobs')->where('uuid', $jobId)->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Failed job not found'
            ], 404);
        }

        Log::info('Failed job deleted', ['job_id' => $jobId]);

        return response()->json([
            'success' => true,
            'message' => 'Failed job deleted successfully'
        ]);
    }
}