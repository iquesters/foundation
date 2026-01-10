<?php
// app/Helpers/TimeHelper.php
use Carbon\Carbon;

if (!function_exists('display_time')) {
    /**
     * Format a single time to 12-hour format (hh:mm A), with optional fallback.
     *
     * @param mixed $time
     * @param string $fallback
     * @return string
     */
    function display_time($time, string $fallback = 'N/A'): string
    {
        if (!$time) {
            return $fallback;
        }

        try {
            return Carbon::parse($time)->format('h:i A');
        } catch (\Exception $e) {
            return $fallback;
        }
    }
}