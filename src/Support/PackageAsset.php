<?php

namespace Iquesters\Foundation\Support;

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

    /** A cacheable file response, or a 404. */
    public static function response(string $publicDir, string $path): BinaryFileResponse
    {
        $file = self::resolve($publicDir, $path);

        if ($file === null) {
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
