<?php

namespace Iquesters\Foundation\Helpers;

use Carbon\Carbon;
use DateTimeInterface;

class DateTimeHelper
{
    /**
     * ---------------------------------
     * CORE DATE RESOLVER (single source)
     * ---------------------------------
     */
    private static function resolveDate(string|DateTimeInterface $time): Carbon
    {
        $timezone = session('timezone', config('app.timezone'));

        return Carbon::parse($time)->setTimezone($timezone);
    }

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
     * ---------------------------------
     * SMART CHAT LIST FORMAT
     * ---------------------------------
     */
    public static function displaySmart(
        string|DateTimeInterface|null $time,
        string $fallback = 'N/A'
    ): string {
        if (!$time) return $fallback;

        try {
            $date = self::resolveDate($time);

            if ($date->isToday()) {
                return $date->format('h:i A');
            }

            if ($date->isYesterday()) {
                return 'Yesterday';
            }

            if ($date->isCurrentWeek()) {
                return $date->format('D');
            }

            if ($date->isCurrentYear()) {
                return $date->format('d M');
            }

            return $date->format('d/m/Y');

        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * ---------------------------------
     * CONVERSATIONAL MESSAGE FORMAT
     * ---------------------------------
     */
    public static function displayConversational(
        string|DateTimeInterface|null $time,
        string $fallback = 'N/A'
    ): string {
        if (!$time) return $fallback;

        try {
            $date = self::resolveDate($time);

            if ($date->isToday()) {
                return $date->format('h:i A');
            }

            if ($date->isYesterday()) {
                return 'Yesterday ' . $date->format('h:i A');
            }

            if ($date->isCurrentWeek()) {
                return $date->format('l h:i A');
            }

            if ($date->isCurrentYear()) {
                return $date->format('d F, h:i A');
            }

            return $date->format('d F Y, h:i A');

        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * ---------------------------------
     * LOCAL / BROWSER TIMEZONE
     * ---------------------------------
     */
    private static function displayFromLocal(string|DateTimeInterface $time, string $fallback): string
    {
        try {
            return self::resolveDate($time)->format('h:i A');
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private static function displayDateTimeFromLocal(string|DateTimeInterface $time, string $fallback): string
    {
        try {
            return self::resolveDate($time)->format('d M Y, h:i A');
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * ---------------------------------
     * USER SETTINGS (future support)
     * ---------------------------------
     */
    private static function hasUserDateTimeSettings(): bool
    {
        // future example:
        // return auth()->check() && auth()->user()?->timezone;
        return false;
    }

    private static function displayFromUserSettings(string|DateTimeInterface $time, string $fallback): string
    {
        try {
            return self::resolveDate($time)->format('h:i A');
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private static function displayDateTimeFromUserSettings(string|DateTimeInterface $time, string $fallback): string
    {
        try {
            return self::resolveDate($time)->format('d M Y, h:i A');
        } catch (\Throwable) {
            return $fallback;
        }
    }
}