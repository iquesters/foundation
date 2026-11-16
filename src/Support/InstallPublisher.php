<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Publishes a vendor package's migration/config into the host app once.
 *
 * Re-publishing a migration creates a second, timestamped copy and `migrate`
 * then fails ("table already exists"), so a migration is published only when
 * neither a published copy nor the table exists. Used by module install steps
 * (BaseServiceProvider::onPackageDiscover).
 */
final class InstallPublisher
{
    /**
     * @param string      $tag        vendor:publish tag, e.g. 'sanctum-migrations'
     * @param string      $fileSuffix published file name ending, e.g. '_create_personal_access_tokens_table.php'
     * @param string|null $table      table the migration creates (skip when it already exists)
     * @return string|null message for the console, or null when nothing was done
     */
    public static function migration(string $tag, string $fileSuffix, ?string $table = null): ?string
    {
        if (glob(database_path('migrations') . '/*' . $fileSuffix)) {
            return null;
        }

        if ($table && self::tableExists($table)) {
            return null;
        }

        Artisan::call('vendor:publish', ['--tag' => $tag]);

        return "Published {$tag}. Run 'php artisan migrate' to create the table.";
    }

    /**
     * @param string $tag  vendor:publish tag, e.g. 'sanctum-config'
     * @param string $file config file name, e.g. 'sanctum.php'
     */
    public static function config(string $tag, string $file): ?string
    {
        if (file_exists(config_path($file))) {
            return null;
        }

        Artisan::call('vendor:publish', ['--tag' => $tag]);

        return "Published {$tag} (config/{$file}).";
    }

    private static function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false; // database not reachable during install; rely on the file check
        }
    }
}
