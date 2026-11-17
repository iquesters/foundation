<?php

namespace Iquesters\Foundation\System\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Consistent logging: every line is prefixed with [Class::method(line)].
 *
 * All methods are static, so they work from instance code ($this->logInfo())
 * and from static helpers (static::logInfo()). An optional $context array is
 * passed through to the log channel.
 */
trait Loggable
{
    private static array $methodStartTimes = [];

    /**
     * Log method start WITH timing tracking
     */
    protected static function logMethodStart(string $message = '', array $context = []): void
    {
        [$classShort, $method, $line] = static::logCaller(2);
        $key = "{$classShort}::{$method}";

        $logMessage = "[$classShort::{$method}({$line})] 👉 STARTED";
        if ($message) {
            $logMessage .= " - {$message}";
        }

        Log::info($logMessage, $context);
        self::$methodStartTimes[$key] = microtime(true);
    }

    /**
     * Log method end WITH duration calculation
     */
    protected static function logMethodEnd(string $message = '', array $context = []): void
    {
        [$classShort, $method, $line] = static::logCaller(2);
        $key = "{$classShort}::{$method}";

        if (! isset(self::$methodStartTimes[$key])) {
            Log::warning("[$classShort::{$method}({$line})] No start time found");

            return;
        }

        $durationMs = (microtime(true) - self::$methodStartTimes[$key]) * 1000;
        unset(self::$methodStartTimes[$key]);

        $logMessage = "[$classShort::{$method}({$line})] ✅ COMPLETED";
        if ($message) {
            $logMessage .= " - {$message}";
        }

        Log::info($logMessage, $context + [
            'duration_ms' => round($durationMs, 2),
            'duration_s' => round($durationMs / 1000, 3),
        ]);
    }

    // 🚨 EMERGENCY
    protected static function logEmergency(string $message = '', array $context = []): void
    {
        static::logActual('emergency', $message, $context);
    }

    // 🚨 ALERT
    protected static function logAlert(string $message = '', array $context = []): void
    {
        static::logActual('alert', $message, $context);
    }

    // 💥 CRITICAL
    protected static function logCritical(string $message = '', array $context = []): void
    {
        static::logActual('critical', $message, $context);
    }

    // ❌ ERROR
    protected static function logError(string $message = '', array $context = []): void
    {
        static::logActual('error', $message, $context);
    }

    // ⚠️ WARNING
    protected static function logWarning(string $message = '', array $context = []): void
    {
        static::logActual('warning', $message, $context);
    }

    // ℹ️ NOTICE
    protected static function logNotice(string $message = '', array $context = []): void
    {
        static::logActual('notice', $message, $context);
    }

    // 💬 INFO
    protected static function logInfo(string $message = '', array $context = []): void
    {
        static::logActual('info', $message, $context);
    }

    // 🐛 DEBUG
    protected static function logDebug(string $message = '', array $context = []): void
    {
        static::logActual('debug', $message, $context);
    }

    /**
     * Core method: "[Class::method(line)] message" plus optional context.
     */
    private static function logActual(string $level, string $message, array $context = []): void
    {
        [$classShort, $method, $line] = static::logCaller(3);

        Log::{$level}("[$classShort::{$method}({$line})] {$message}", $context);
    }

    /**
     * Class, calling method and line for the log prefix.
     * $depth = frames between the caller and this method (2 when called
     * directly from logMethodStart/End, 3 via logInfo() etc. -> logActual()).
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private static function logCaller(int $depth): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1);

        return [
            class_basename(static::class),
            $trace[$depth]['function'] ?? 'unknown',
            $trace[$depth - 1]['line'] ?? 0,
        ];
    }
}
