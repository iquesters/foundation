<?php

namespace Iquesters\Foundation\Support;

use Iquesters\Foundation\System\Traits\Loggable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a module's public/ files (js, css, images, fonts) safely.
 *
 * The requested path is resolved with realpath() and must stay inside the
 * module's public directory, so "../" (raw or URL-encoded) can't reach .env,
 * composer.json or any other file. Only static asset types are served.
 */
final class PackageAsset
{
    use Loggable;

    public const MIME_TYPES = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'map' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    /** Absolute path of the asset, or null if it is missing or not allowed. */
    public static function resolve(string $publicDir, string $path): ?string
    {
        $root = realpath($publicDir);

        if ($root === false || $path === '' || str_contains($path, "\0")) {
            return null;
        }

        $file = realpath($root . DIRECTORY_SEPARATOR . $path);

        if ($file === false || ! is_file($file)) {
            return null;
        }

        // Must be inside the public directory (compare with a trailing separator
        // so "/public-other" doesn't pass as "/public").
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (! str_starts_with($file, $rootPrefix)) {
            return null;
        }

        // No hidden files or folders (.env, .git, ...).
        $relative = substr($file, strlen($rootPrefix));
        foreach (preg_split('#[\\\\/]#', $relative) as $segment) {
            if (str_starts_with($segment, '.')) {
                return null;
            }
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return isset(self::MIME_TYPES[$extension]) ? $file : null;
    }

    /**
     * URL for a module asset.
     *
     * If the module's public/ files were published to public/{publishedDir}
     * (`php artisan vendor:publish --tag=laravel-assets --force`, which every
     * Laravel app runs after `composer update`) and the published copy is up to
     * date, return the static URL so the web server serves it without booting
     * Laravel. Otherwise fall back to the module's asset route, which always
     * serves the current file. The ?v= value changes whenever the module file
     * changes, so browsers fetch new versions despite long cache headers.
     */
    public static function url(string $publicDir, string $publishedDir, string $path, string $routeName): string
    {
        $path = ltrim($path, '/');
        $source = rtrim($publicDir, '/\\') . '/' . $path;

        if (! is_file($source)) {
            return route($routeName, ['path' => $path]);
        }

        $version = (string) filemtime($source);
        $published = public_path(trim($publishedDir, '/') . '/' . $path);

        if (self::isPublishedCopyCurrent($source, $published)) {
            return asset(trim($publishedDir, '/') . '/' . $path) . '?v=' . $version;
        }

        return route($routeName, ['path' => $path, 'v' => $version]);
    }

    /**
     * A copy made by vendor:publish is current when it has the same size and is
     * at least as new as the module file (publishing writes a fresh mtime).
     * After a module update the module file is newer, so the stale copy is
     * skipped until assets are re-published.
     */
    public static function isPublishedCopyCurrent(string $source, string $published): bool
    {
        return is_file($published)
            && filesize($published) === filesize($source)
            && filemtime($published) >= filemtime($source);
    }

    /** A cacheable file response, or a 404. */
    public static function response(string $publicDir, string $path): BinaryFileResponse
    {
        $file = self::resolve($publicDir, $path);

        if ($file === null) {
            // "../" in an asset path is an attempt to read files outside public/.
            if (str_contains(str_replace('\\', '/', $path), '../') || str_contains($path, "\0")) {
                static::logWarning('Blocked asset path traversal attempt', [
                    'path' => $path,
                    'ip' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]);
            }

            abort(404);
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return response()->file($file, [
            'Content-Type' => self::MIME_TYPES[$extension],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Last-Modified' => gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
