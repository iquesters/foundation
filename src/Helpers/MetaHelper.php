<?php

namespace Iquesters\Foundation\Helpers;

class MetaHelper
{
    /**
     * Generate the page title.
     */
    public static function make(array $titles = []): string
    {
        $filtered = array_filter($titles);
        $filtered[] = config('app.name', 'Iquesters');
        return implode(' | ', $filtered);
    }

    /**
     * Generate the meta description.
     */
    public static function description(?string $description = null): string
    {
        return $description ?? 'Default meta description for Iquesters.';
    }
}