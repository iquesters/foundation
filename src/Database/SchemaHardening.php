<?php

namespace Iquesters\Foundation\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Helpers for migrations that tighten existing module tables in place.
 * Every method is idempotent, so it is safe on fresh and existing installs.
 */
final class SchemaHardening
{
    use Loggable;

    /**
     * Remove duplicate (ref_parent, meta_key) rows, keeping the newest, then
     * add a unique index (which also serves meta lookups).
     */
    public static function uniqueMetaKeys(string $table): void
    {
        if (! Schema::hasTable($table) || self::hasIndex($table, "{$table}_ref_parent_meta_key_unique")) {
            return;
        }

        $removed = DB::delete(
            "DELETE older FROM `{$table}` older
             JOIN `{$table}` newer
               ON newer.ref_parent <=> older.ref_parent
              AND newer.meta_key = older.meta_key
              AND newer.id > older.id"
        );

        Schema::table($table, function (Blueprint $t) {
            $t->unique(['ref_parent', 'meta_key']);
        });

        self::logInfo("Unique (ref_parent, meta_key) added to {$table}", ['duplicate_rows_removed' => $removed]);
    }

    /** Widen a column to LONGTEXT (values over 255 chars were being truncated). */
    public static function longText(string $table, string $column, bool $nullable = false): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column, $nullable) {
            $col = $t->longText($column);
            $nullable ? $col->nullable()->change() : $col->change();
        });
    }

    /**
     * Turn created_by / updated_by into nullable foreign keys to users.
     * 0 and ids of users that no longer exist become NULL first.
     */
    public static function auditForeignKeys(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable('users')) {
            return;
        }

        foreach (['created_by', 'updated_by'] as $column) {
            if (! Schema::hasColumn($table, $column) || self::hasForeignKey($table, "{$table}_{$column}_foreign")) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->unsignedBigInteger($column)->nullable()->default(null)->change();
            });

            $nulled = DB::table($table)
                ->where(function ($q) use ($column) {
                    $q->where($column, 0)
                        ->orWhereNotIn($column, DB::table('users')->select('id'));
                })
                ->update([$column => null]);

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->foreign($column)->references('id')->on('users')->nullOnDelete();
            });

            self::logInfo("{$table}.{$column} is now a nullable FK to users", ['rows_set_to_null' => $nulled]);
        }
    }

    /** Reverse of auditForeignKeys (keeps the columns nullable). */
    public static function dropAuditForeignKeys(string $table): void
    {
        foreach (['created_by', 'updated_by'] as $column) {
            if (Schema::hasTable($table) && self::hasForeignKey($table, "{$table}_{$column}_foreign")) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
            }
        }
    }

    public static function dropUniqueMetaKeys(string $table): void
    {
        if (Schema::hasTable($table) && self::hasIndex($table, "{$table}_ref_parent_meta_key_unique")) {
            Schema::table($table, fn (Blueprint $t) => $t->dropUnique(['ref_parent', 'meta_key']));
        }
    }

    private static function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($index) => $index['name'] === $name);
    }

    private static function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn ($fk) => $fk['name'] === $name);
    }
}
