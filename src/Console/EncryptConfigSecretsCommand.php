<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Iquesters\Foundation\Support\ConfSecret;
use Iquesters\Foundation\Support\Settings\SettingsStore;

/**
 * Safe to re-run: encrypts plain-text secret values (keys containing
 * secret/token/password/api_key, see ConfSecret) in the stored module
 * settings (module_settings). Settings saved through BaseConf::update() or
 * the admin screen are already encrypted; this catches values imported or
 * edited directly in the database.
 */
class EncryptConfigSecretsCommand extends Command
{
    protected $signature = 'conf:encrypt-secrets {--dry-run : List what would be encrypted without writing}';

    protected $description = 'Encrypt plain-text secret values in the stored module settings';

    public function handle(): int
    {
        if (! SettingsStore::tableReady()) {
            $this->warn('The module_settings table does not exist yet: run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $total = 0;

        foreach (DB::table(SettingsStore::TABLE)->get(['module', 'settings']) as $row) {
            $settings = json_decode((string) $row->settings, true);
            if (! is_array($settings)) {
                continue;
            }

            $plain = $this->plainSecrets($settings);
            if ($plain === []) {
                continue;
            }

            foreach ($plain as $path) {
                $this->line(($this->option('dry-run') ? '[dry-run] ' : '') . "Encrypting {$row->module}: {$path}");
            }

            if (! $this->option('dry-run')) {
                SettingsStore::put($row->module, $settings); // put() encrypts secrets and clears the cache
            }

            $total += count($plain);
        }

        $this->info("{$total} secret value(s) " . ($this->option('dry-run') ? 'would be encrypted.' : 'encrypted.'));

        return self::SUCCESS;
    }

    /** Dot paths of sensitive values that are stored in plain text. */
    private function plainSecrets(array $values, string $prefix = ''): array
    {
        $paths = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                array_push($paths, ...$this->plainSecrets($value, $path));
            } elseif (is_string($value) && $value !== '' && ConfSecret::isSensitive((string) $key) && ! ConfSecret::isEncrypted($value)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
