<?php

namespace Iquesters\Foundation\Services\Entity;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\Entity as FoundationEntity;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Resolves everything the generic entity API needs to know about a table:
 * its entity definition (the allowlist), field definitions, columns, meta table
 * and reference column. Schema introspection is memoized for the request.
 */
class EntitySchemaResolver
{
    use Loggable;

    /** Columns the API never writes from a payload. */
    public const SYSTEM_COLUMNS = [
        'id', 'uid', 'status', 'created_by', 'updated_by', 'deleted_by',
        'created_at', 'updated_at', 'deleted_at',
    ];

    private array $definitions = [];

    private array $columns = [];

    private array $metaTables = [];

    /**
     * Allowlist: only tables backed by a registered entity definition are
     * reachable. Both failures use the same message so callers can't probe
     * which tables exist.
     */
    public function ensureEntityTableExists(string $table): FoundationEntity
    {
        if ($table === '') {
            throw new \InvalidArgumentException('Entity name is required');
        }

        try {
            $definition = $this->definition($table);
        } catch (\RuntimeException) {
            $this->logWarning("Entity access denied: no entity definition for '{$table}'");
            throw new \RuntimeException("Entity {$table} not found");
        }

        if (! Schema::hasTable($table)) {
            $this->logWarning("Entity fetch failed: Table '{$table}' not found");
            throw new \RuntimeException("Entity {$table} not found");
        }

        return $definition;
    }

    public function definition(string $table): FoundationEntity
    {
        if (isset($this->definitions[$table])) {
            return $this->definitions[$table];
        }

        $entity = FoundationEntity::query()
            ->whereHas('metas', function ($query) use ($table) {
                $query->where(function ($q) use ($table) {
                    $q->where('meta_key', 'published_table_name')->where('meta_value', $table);
                })->orWhere(function ($q) use ($table) {
                    $q->where('meta_key', 'table_name')->where('meta_value', $table);
                });
            })
            ->first();

        if (! $entity) {
            throw new \RuntimeException("Entity definition for table {$table} was not found.");
        }

        return $this->definitions[$table] = $entity;
    }

    /** @return array{0: array, 1: array} [main field definitions, meta field definitions] */
    public function fieldDefinitions(string $table): array
    {
        $definition = $this->definition($table);

        return [$this->mainFieldDefinitions($definition), $this->metaFieldDefinitions($definition)];
    }

    public function mainFieldDefinitions(FoundationEntity $definition): array
    {
        $fields = is_array($definition->fields) ? $definition->fields : [];
        $result = [];

        foreach ($fields as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = $field['name'] ?? (is_string($key) ? $key : null);
            if (! $name) {
                continue;
            }

            $field['name'] = $name;
            $result[$name] = $field;
        }

        return $result;
    }

    public function metaFieldDefinitions(FoundationEntity $definition): array
    {
        $fields = is_array($definition->meta_fields) ? $definition->meta_fields : [];
        $result = [];

        foreach ($fields as $field) {
            if (is_array($field) && ! empty($field['meta_key'])) {
                $result[$field['meta_key']] = $field;
            }
        }

        return $result;
    }

    public function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    /** Columns a payload may write: defined in the entity and not system-managed. */
    public function writableColumns(string $table, array $mainDefinitions): array
    {
        return array_values(array_filter(
            $this->columns($table),
            fn ($column) => array_key_exists($column, $mainDefinitions)
                && ! in_array($column, self::SYSTEM_COLUMNS, true)
        ));
    }

    public function metaTable(string $table): ?string
    {
        if (array_key_exists($table, $this->metaTables)) {
            return $this->metaTables[$table];
        }

        $candidates = [
            "{$table}_metas",
            "{$table}_meta",
            Str::singular($table).'_meta',
            Str::singular($table).'_metas',
        ];

        return $this->metaTables[$table] = collect($candidates)->first(fn ($t) => Schema::hasTable($t));
    }

    public function referenceColumn(string $table, string $reference): string
    {
        $hasUid = $this->hasColumn($table, 'uid');

        if ($hasUid && is_numeric($reference)) {
            throw new \InvalidArgumentException(
                "This table uses 'uid' as the primary reference. Please pass a valid UID instead of an ID."
            );
        }

        if ($hasUid) {
            return 'uid';
        }

        if ($this->hasColumn($table, 'id')) {
            return 'id';
        }

        throw new \InvalidArgumentException("Table {$table} has no 'uid' or 'id' column to filter by.");
    }

    /**
     * Everything store/update need, in one structure.
     *
     * @return array{table_columns: array, entity_definition: FoundationEntity, main_field_definitions: array, meta_field_definitions: array, writable_main_columns: array}
     */
    public function writeSchema(string $table): array
    {
        $definition = $this->ensureEntityTableExists($table);
        $main = $this->mainFieldDefinitions($definition);

        return [
            'table_columns' => $this->columns($table),
            'entity_definition' => $definition,
            'main_field_definitions' => $main,
            'meta_field_definitions' => $this->metaFieldDefinitions($definition),
            'writable_main_columns' => $this->writableColumns($table, $main),
        ];
    }
}
