<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module APIs are now declared in composer.json (extra.platform.api), so the
 * DB-backed `api_conf` settings of these modules are gone from their conf
 * classes. Remove the stored rows, otherwise every conf load logs
 * "Property 'api_conf' missing".
 *
 * Only these four modules: other modules may still declare api_conf.
 */
return new class extends Migration
{
    private const CONF_KEYS = [
        'foundation-conf',
        'user-interface-conf',
        'user-management-conf',
        'organisation-conf',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('master_data') || ! Schema::hasTable('master_data_metas')) {
            return;
        }

        $confIds = DB::table('master_data')->whereIn('key', self::CONF_KEYS)->pluck('id');

        if ($confIds->isEmpty()) {
            return;
        }

        DB::table('master_data_metas')
            ->whereIn('ref_parent', $confIds)
            ->where('meta_key', 'like', '$API\_CONF~%')
            ->delete();

        // Cached conf trees still contain the removed keys.
        foreach (['iquesters_foundation', 'iquesters_user-interface', 'iquesters_user-management', 'iquesters_organisation'] as $identifier) {
            Cache::forget("conf_{$identifier}_flattened");
        }
    }

    public function down(): void
    {
        // Nothing to restore: re-running the module seeders writes conf defaults again.
    }
};
