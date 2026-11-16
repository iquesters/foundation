<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Support\Facades\Route;

/**
 * Standard handling of a module's public/ files. A module adds a small
 * subclass (e.g. Iquesters\UserInterface\Support\Assets) that only declares
 * its slug; this class provides:
 *
 * - registerRoutes(): GET /module-assets/{slug}/{path} (+ legacy /vendor/{slug}/{path}),
 *   serving only files inside public/ (no traversal, static types only)
 * - url($file): the published copy in public/vendor/{slug} when it is current,
 *   otherwise the route (see PackageAsset)
 * - resolveUrl($value): a configured logo/asset value (full URL, absolute path,
 *   file in the app's public/, "pkg::file" or a path in this module) → URL
 *
 * The module also publishes public/ under the 'laravel-assets' tag to
 * public_path("vendor/{slug}") in its service provider.
 */
abstract class ModuleAssets
{
    /** URL/folder segment, e.g. 'userinterface' (→ /vendor/userinterface, route userinterface.asset). */
    abstract protected static function slug(): string;

    /** Absolute path of the module's public/ dir (found from the module's composer.json). */
    public static function publicPath(): string
    {
        static $paths = [];

        return $paths[static::class] ??= self::packageRoot() . '/public';
    }

    public static function routeName(): string
    {
        return static::slug() . '.asset';
    }

    /**
     * The /module-assets/ route never collides with the published copy, which
     * the web server serves directly.
     */
    public static function registerRoutes(): void
    {
        $slug = static::slug();
        $publicPath = static::publicPath();
        $serve = fn ($path) => PackageAsset::response($publicPath, $path);

        Route::get("/module-assets/{$slug}/{path}", $serve)->where('path', '.*')->name(static::routeName());

        // Legacy URL, kept for hard-coded links (when assets are not published).
        Route::get("/vendor/{$slug}/{path}", $serve)->where('path', '.*')->name(static::routeName() . '.legacy');
    }

    public static function url(string $file): string
    {
        return PackageAsset::url(static::publicPath(), 'vendor/' . static::slug(), ltrim($file, '/'), static::routeName());
    }

    /**
     * A configured asset value (e.g. a logo setting) → URL:
     * full URL or absolute path as is; a file in the app's public/ via asset()
     * (when $allowAppPublic); "pkg::img/logo.png" or a relative path → this module's file.
     */
    public static function resolveUrl(string $value, bool $allowAppPublic = true): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, '/')) {
            return $value;
        }

        if ($allowAppPublic && $value !== '' && file_exists(public_path($value))) {
            return asset($value);
        }

        return static::url(str_replace('::', '/', $value));
    }

    private static function packageRoot(): string
    {
        $path = dirname((new \ReflectionClass(static::class))->getFileName());

        while (!file_exists($path . '/composer.json') && dirname($path) !== $path) {
            $path = dirname($path);
        }

        return $path;
    }
}
