<?php

namespace Iquesters\Foundation\Helpers;

use Carbon\Carbon;
use DateTimeInterface;

class DateTimeHelper
{
    // | Scenario            | sessionStorage  | Detection    | Server Call? | Final Value |
    // | ------------------- | --------------- | ------------ | ------------ | ----------- |
    // | First load          | Empty           | Fresh        | ✅ YES        | Fresh       |
    // | Reload (no change)  | Cached          | Fresh (same) | ❌ NO         | Cached      |
    // | Reload (DST change) | Cached (old)    | Fresh (new)  | ✅ YES        | Fresh       |
    // | Reload (travel)     | Cached (IST)    | Fresh (EST)  | ✅ YES        | Fresh       |
    // | Tab close + reopen  | Empty (new tab) | Fresh        | ✅ YES        | Fresh       |
    
    /**
     * Display time based on user settings or local/browser timezone.
     */
    public static function display(
        string|DateTimeInterface|null $time,
        string $fallback = 'N/A'
    ): string {
        if (!$time) return $fallback;

        if (self::hasUserDateTimeSettings()) {
            return self::displayFromUserSettings($time, $fallback);
        }

        return self::displayFromLocal($time, $fallback);
    }

    /**
     * Display full datetime based on user settings or local/browser timezone.
     */
    public static function displayDateTime(
        string|DateTimeInterface|null $time,
        string $fallback = 'N/A'
    ): string {
        if (!$time) return $fallback;

        if (self::hasUserDateTimeSettings()) {
            return self::displayDateTimeFromUserSettings($time, $fallback);
        }

        return self::displayDateTimeFromLocal($time, $fallback);
    }

    /**
     * -------------------------
     * LOCAL / BROWSER TIMEZONE
     * -------------------------
     */
    private static function displayFromLocal(string|DateTimeInterface $time, string $fallback): string
    {
        try {
            $timezone = session('timezone', config('app.timezone')); // browser -> session -> fallback
            return Carbon::parse($time)
                ->setTimezone($timezone)
                ->format('h:i A'); // default 12h format
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private static function displayDateTimeFromLocal(string|DateTimeInterface $time, string $fallback): string
    {
        try {
            $timezone = session('timezone', config('app.timezone'));
            \Log::info('Using timezone in DateTimeHelper', [
                'timezone' => $timezone,
                'time_from_db' => $time,
            ]);
            return Carbon::parse($time)
                ->setTimezone($timezone)
                ->format('d M Y, h:i A'); // default full datetime format
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * -------------------------
     * USER SETTINGS (stub)
     * -------------------------
     */
    private static function hasUserDateTimeSettings(): bool
    {
        // example for future implementation:
        // return auth()->check() && auth()->user()?->timezone && auth()->user()?->time_format;
        return false;
    }

    private static function displayFromUserSettings(string|DateTimeInterface $time, string $fallback): string
    {
        // TODO: implement user timezone + user time format
    }

    private static function displayDateTimeFromUserSettings(string|DateTimeInterface $time, string $fallback): string
    {
        // TODO: implement user timezone + user datetime format
    }
}