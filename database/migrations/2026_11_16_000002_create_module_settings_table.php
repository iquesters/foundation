<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Support\Settings\SettingsStore;

/**
 * Module settings move from master_data "{module}-conf" + master_data_metas
 * (one row per flattened key like "$SOCIAL_LOGIN~@O_AUTH_PROVIDERS~$GOOGLE~ENABLED")
 * to one JSON document per module in `module_settings`.
 *
 * Only values that differ from the code defaults are kept (defaults now come
 * from the settings classes, not the database). The old rows are left in place,
 * unused, so nothing is lost; they can be removed later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SettingsStore::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('module')->unique();      // e.g. iquesters/user-management
            $table->json('settings');                // overrides of the code defaults
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('master_data') || ! Schema::hasTable('master_data_metas')) {
            return;
        }

        // master_data key "{name}-conf" → module identifier, for installed modules.
        $modulesByConfKey = [];
        foreach (ConfProvider::registered() as $module => $class) {
            $modulesByConfKey[Str::afterLast($module, '/').'-conf'] = [$module, $class];
        }

        foreach (DB::table('master_data')->where('key', 'like', '%-conf')->get(['id', 'key']) as $conf) {
            $legacy = SettingsStore::fromLegacyRows(
                DB::table('master_data_metas')->where('ref_parent', $conf->id)->get(['meta_key', 'meta_value'])
            );

            if ($legacy === []) {
                continue;
            }

            if (isset($modulesByConfKey[$conf->key])) {
                [$module, $class] = $modulesByConfKey[$conf->key];
                $overrides = (new $class)->overridesFrom($legacy);
            } else {
                // Module not installed in this app: keep its values as they were.
                $module = 'iquesters/'.Str::beforeLast($conf->key, '-conf');
                $overrides = $legacy;
            }

            if ($overrides !== []) {
                SettingsStore::put($module, $overrides);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(SettingsStore::TABLE);
    }
};
