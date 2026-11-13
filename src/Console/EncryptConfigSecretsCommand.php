<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Iquesters\Foundation\Support\ConfSecret;

/**
 * One-time (and safe to re-run) migration: encrypts plain-text secret values
 * already stored in master_data_metas for module configs (*-conf).
 */
class EncryptConfigSecretsCommand extends Command
{
    protected $signature = 'conf:encrypt-secrets {--dry-run : List what would be encrypted without writing}';

    protected $description = 'Encrypt plain-text secret config values (secret/token/password/api_key keys) in master_data_metas';

    public function handle(): int
    {
        $confIds = DB::table('master_data')->where('key', 'like', '%-conf')->pluck('id');

        $rows = DB::table('master_data_metas')
            ->whereIn('ref_parent', $confIds)
            ->get(['id', 'meta_key', 'meta_value']);

        $count = 0;

        foreach ($rows as $row) {
            if (! ConfSecret::isSensitive($row->meta_key)
                || $row->meta_value === null
                || $row->meta_value === ''
                || ConfSecret::isEncrypted($row->meta_value)) {
                continue;
            }

            $this->line(($this->option('dry-run') ? '[dry-run] ' : '') . "Encrypting {$row->meta_key} (meta id {$row->id})");

            if (! $this->option('dry-run')) {
                DB::table('master_data_metas')
                    ->where('id', $row->id)
                    ->update(['meta_value' => ConfSecret::encrypt($row->meta_value)]);
            }

            $count++;
        }

        if ($count > 0 && ! $this->option('dry-run')) {
            // Config is cached with rememberForever; drop it so encrypted values are reloaded.
            Cache::flush();
        }

        $this->info("{$count} secret value(s) " . ($this->option('dry-run') ? 'would be encrypted.' : 'encrypted.'));

        return self::SUCCESS;
    }
}
