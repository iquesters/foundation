<?php

namespace Iquesters\Foundation\System\Traits;

use Illuminate\Support\Facades\Log;

trait AutoLogger
{
    private static array $methodStartTimes = [];

    /**
     * Log method start WITH timing tracking
     */
    protected function logMethodStart(string $message = ''): void
    {
        $trace_depth = 2; // should not be less than 2
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace_depth);

        $classShort = class_basename($caller['class'] ?? static::class);
        $method = $trace[$trace_depth - 1]['function'] ?? 'unknown';
        $line = $trace[$trace_depth - 2]['line'] ?? 0;
        $key = "{$classShort}::{$method}";

        $logMessage = "[$classShort::{$method}({$line})] 👉 STARTED";
        if ($message) $logMessage .= " - {$message}";

        Log::info($logMessage);
        self::$methodStartTimes[$key] = microtime(true);
    }

    /**
     * Log method end WITH duration calculation
     */
    protected function logMethodEnd(string $message = ''): void
    {
        $trace_depth = 2; // should not be less than 2
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace_depth);

        $classShort = class_basename($caller['class'] ?? static::class);
        $method = $trace[$trace_depth - 1]['function'] ?? 'unknown';
        $line = $trace[$trace_depth - 2]['line'] ?? 0;
        $key = "{$classShort}::{$method}";

        if (!isset(self::$methodStartTimes[$key])) {
            Log::warning("[$classShort::{$method}({$line})] No start time found");
            return;
        }

        $durationMs = (microtime(true) - self::$methodStartTimes[$key]) * 1000;
        unset(self::$methodStartTimes[$key]);

        $logMessage = "[$classShort::{$method}({$line})] ✅ COMPLETED";
        if ($message) $logMessage .= " - {$message}";

        Log::info($logMessage, [
            'duration_ms' => round($durationMs, 2),
            'duration_s' => round($durationMs / 1000, 3)
        ]);
    }

    // 🚨 EMERGENCY
    protected function logEmergency(string $message = ''): void
    {
        $this->logActual('emergency', $message);
    }

    // 🚨 ALERT
    protected function logAlert(string $message = ''): void
    {
        $this->logActual('alert', $message);
    }

    // 💥 CRITICAL
    protected function logCritical(string $message = ''): void
    {
        $this->logActual('critical', $message);
    }

    // ❌ ERROR
    protected function logError(string $message = ''): void
    {
        $this->logActual('error', $message);
    }

    // ⚠️ WARNING
    protected function logWarning(string $message = ''): void
    {
        $this->logActual('warning', $message);
    }

    // ℹ️ NOTICE
    protected function logNotice(string $message = ''): void
    {
        $this->logActual('notice', $message);
    }

    // 💬 INFO
    protected function logInfo(string $message = ''): void
    {
        $this->logActual('info', $message);
    }

    // 🐛 DEBUG
    protected function logDebug(string $message = ''): void
    {
        $this->logActual('debug', $message);
    }

    /**
     * Core method - Smart logging: CLEAN when empty, RICH when context/timing
     */
    private function logActual(string $level, string $message): void
    {
        $trace_depth = 3; // should not be less than 2
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace_depth);

        $classShort = class_basename($caller['class'] ?? static::class);
        $method = $trace[$trace_depth - 1]['function'] ?? 'unknown';
        $line = $trace[$trace_depth - 2]['line'] ?? 0;
        $key = "{$classShort}::{$method}";

        $fullMessage = "[$classShort::{$method}({$line})] {$message}";

        Log::{$level}($fullMessage);
    }
}
