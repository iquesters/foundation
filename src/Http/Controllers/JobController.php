<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobController extends Controller
{
    public function index()
    {
        try {
            // Get current queue statistics
            $queues = DB::table('jobs')
                ->select(
                    'queue',
                    DB::raw('COUNT(*) as total_jobs'),
                    DB::raw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as processing_jobs'),
                    DB::raw('SUM(CASE WHEN reserved_at IS NULL THEN 1 ELSE 0 END) as pending_jobs'),
                    DB::raw('MIN(available_at) as oldest_job')
                )
                ->groupBy('queue')
                ->orderBy('total_jobs', 'desc')
                ->get();

            // Get active workers count (jobs reserved in last 5 minutes)
            $activeWorkers = DB::table('jobs')
                ->where('reserved_at', '>=', now()->subMinutes(5))
                ->whereNotNull('reserved_at')
                ->count();

            // Get total statistics
            $totalStats = [
                'total_jobs' => DB::table('jobs')->count(),
                'processing' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
                'pending' => DB::table('jobs')->whereNull('reserved_at')->count(),
                'active_workers' => $activeWorkers,
            ];

            return view('foundation::jobs.current', compact('queues', 'totalStats'));
        } catch (\Throwable $e) {
            Log::error('Jobs index failed', ['exception' => $e]);
            return back()->with('error', 'Unable to load current jobs.');
        }
    }

    public function completed()
    {
        try {
            $since = now()->subHours(24);

            $queues = DB::table('completed_jobs')
                ->where('completed_at', '>=', $since)
                ->select(
                    'queue',
                    DB::raw('COUNT(*) as completed_jobs'),
                    DB::raw('MAX(completed_at) as last_completed_at')
                )
                ->groupBy('queue')
                ->orderBy('completed_jobs', 'desc')
                ->get();

            // Total statistics
            $totalStats = DB::table('completed_jobs')
                ->where('completed_at', '>=', $since)
                ->select(
                    DB::raw('COUNT(*) as total_completed')
                )
                ->first();

            // Calculate throughput (jobs per hour)
            $totalStats->jobs_per_hour = round($totalStats->total_completed / 24, 2);

            return view('foundation::jobs.completed', compact('queues', 'totalStats'));
        } catch (\Throwable $e) {
            Log::error('Jobs completed failed', ['exception' => $e]);
            return back()->with('error', 'Unable to load completed jobs.');
        }
    }

    public function failed()
    {
        try {
            $since = now()->subHours(24);

            $queues = DB::table('failed_jobs')
                ->where('failed_at', '>=', $since)
                ->select(
                    'queue',
                    DB::raw('COUNT(*) as failed_jobs'),
                    DB::raw('MAX(failed_at) as last_failed_at'),
                    DB::raw('COUNT(DISTINCT SUBSTRING_INDEX(exception, "\n", 1)) as unique_exceptions')
                )
                ->groupBy('queue')
                ->orderBy('failed_jobs', 'desc')
                ->get();

            // Get most common exceptions (first line only)
            $commonExceptions = DB::table('failed_jobs')
                ->where('failed_at', '>=', $since)
                ->select(
                    DB::raw('SUBSTRING_INDEX(exception, "\n", 1) as exception_type'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('exception_type')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();

            $totalStats = [
                'total_failed' => DB::table('failed_jobs')->where('failed_at', '>=', $since)->count(),
                'unique_queues' => DB::table('failed_jobs')->where('failed_at', '>=', $since)->distinct('queue')->count(),
            ];

            return view('foundation::jobs.failed', compact('queues', 'commonExceptions', 'totalStats'));
        } catch (\Throwable $e) {
            Log::error('Jobs failed failed', ['exception' => $e]);
            return back()->with('error', 'Unable to load failed jobs.');
        }
    }
}