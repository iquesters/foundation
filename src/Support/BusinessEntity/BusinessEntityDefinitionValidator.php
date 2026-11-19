<?php

namespace Iquesters\Foundation\Support\BusinessEntity;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationType;

/**
 * Checks a business entity definition against the registered entities and
 * the database, and resolves what the structure leaves open:
 *
 * - each entity part's entity exists and is published (its table exists);
 *   the table and entity_uid always come from the entity ("entity": a table
 *   name in definition files is resolved to its uid);
 * - each model part is a registered model (BusinessEntityModels);
 * - the selected fields and meta keys are defined on the entity;
 * - every column and pivot table a link uses already exists, because a
 *   business entity never creates storage. A missing pivot table is
 *   reported in InvalidBusinessEntityDefinition::$missingPivots;
 * - defaults: morph_class (the morph type value: the entity's table, or the
 *   model's morph class) and a reference's label_column.
 */
final class BusinessEntityDefinitionValidator
{
    /** Columns tried, in order, as the label of a referenced record. */
    public const LABEL_COLUMNS = ['name', 'title', 'label', 'display_name', 'email', 'code', 'uid', 'id'];

    public function __construct(private readonly ?BusinessEntityModels $models = null) {}

    /** @throws InvalidArgumentException with every problem found (InvalidBusinessEntityDefinition for database problems) */
    public function validate(array $data): BusinessEntityDefinition
    {
        $definition = BusinessEntityDefinition::fromArray($data);
        $models = $this->models ?? app(BusinessEntityModels::class);
        $errors = [];
        $patches = [];
        $morphClasses = [];

        foreach ($definition->parts() as $alias => $part) {
            if ($part['model'] !== null) {
                if (! $models->has($part['model'])) {
                    $errors[] = "{$this->partName($part)}: \"{$part['model']}\" isn't a platform model this app knows.";

                    continue;
                }

                $table = $models->table($part['model']);

                if (! Schema::hasTable($table)) {
                    $errors[] = "{$this->partName($part)}: the {$part['model']} table ({$table}) doesn't exist in this database.";

                    continue;
                }

                $patches[$alias] = ['table' => $table, 'entity_uid' => null, 'entity' => null];
                $morphClasses[$alias] = $models->morphClass($part['model']);

                continue;
            }

            $entity = $this->findEntity($part);

            if (! $entity) {
                $errors[] = "{$this->partName($part)}: the entity ".($part['entity'] ? "\"{$part['entity']}\" " : '')."doesn't exist (or was deleted).";

                continue;
            }

            $table = self::tableOf($entity);

            if (! $table || ! Schema::hasTable($table)) {
                $errors[] = "{$this->partName($part)}: the entity \"{$entity->entity_name}\" isn't published yet. Publish it first (Entities → {$entity->entity_name} → Publish) so its table exists.";

                continue;
            }

            $patches[$alias] = ['table' => $table, 'entity_uid' => $entity->uid, 'entity' => null];
            // An entity over a platform model's table (e.g. users) is stored in
            // morph columns as that model (App\Models\User), like Spatie does.
            $modelKey = $models->keyForTable($table);
            $morphClasses[$alias] = $modelKey !== null ? $models->morphClass($modelKey) : $table;

            $fieldNames = array_keys(self::mainFields($entity));
            $metaKeys = array_keys(self::metaFields($entity));

            foreach ($part['fields'] as $field) {
                if (! in_array($field['field'], $fieldNames, true)) {
                    $errors[] = "{$this->partName($part)}: \"{$field['field']}\" is not a field of {$entity->entity_name}.";
                }
            }

            foreach ($part['meta_fields'] as $field) {
                if (! in_array($field['meta_key'], $metaKeys, true)) {
                    $errors[] = "{$this->partName($part)}: \"{$field['meta_key']}\" is not a meta field of {$entity->entity_name}.";
                }
            }
        }

        if ($errors !== []) {
            throw new InvalidBusinessEntityDefinition($errors);
        }

        $definition = $definition->withPartData($patches);
        [$errors, $missingPivots, $linkPatches] = $this->checkLinks($definition, $morphClasses);

        if ($errors !== []) {
            throw new InvalidBusinessEntityDefinition($errors, $missingPivots);
        }

        return $definition->withPartData($linkPatches);
    }

    /** @return array{0: string[], 1: array, 2: array} [errors, missing pivots, link patches] */
    private function checkLinks(BusinessEntityDefinition $definition, array $morphClasses): array
    {
        $errors = [];
        $missingPivots = [];
        $patches = [];
        $has = fn (string $table, ?string $column) => $column !== null && Schema::hasColumn($table, $column);
        $missing = function (string $alias, string $table, ?string $column, string $what) use (&$errors, $definition) {
            $name = $this->partName($definition->part($alias));
            $errors[] = "{$name}: the table {$table} has no column \"{$column}\" {$what}. Pick one of its columns.";
        };

        foreach ($definition->parts() as $alias => $part) {
            $link = $part['link'];

            if ($link === null) {
                continue;
            }

            $type = $definition->type($alias);
            $table = $part['table'];
            $parentTable = $definition->part($link['to'])['table'];
            $patch = [];

            switch ($type->holder()) {
                case RelationType::HOLDER_SELF:
                    if (! $has($table, $link['foreign_key'])) {
                        $missing($alias, $table, $link['foreign_key'], 'to store the link');
                    }
                    if ($type->morph() !== null && ! $has($table, $link['morph_type'])) {
                        $missing($alias, $table, $link['morph_type'], 'for the record type');
                    }
                    break;

                case RelationType::HOLDER_PARENT:
                    if (! $has($parentTable, $link['foreign_key'])) {
                        $missing($alias, $parentTable, $link['foreign_key'], 'to store the link');
                    }
                    break;

                case RelationType::HOLDER_PIVOT:
                    $pivot = $link['pivot'];
                    $columns = array_values(array_filter([$pivot['foreign_pivot_key'], $pivot['related_pivot_key'], $pivot['morph_type'] ?? null]));

                    if (! Schema::hasTable($pivot['table'])) {
                        $errors[] = "{$this->partName($part)}: the link table \"{$pivot['table']}\" doesn't exist yet. Create it as an entity with the columns ".implode(', ', $columns).', publish it, then come back. A business entity never creates tables.';
                        $missingPivots[] = ['part' => $alias, 'table' => $pivot['table'], 'columns' => $columns];
                        break;
                    }

                    foreach ($columns as $column) {
                        if (! $has($pivot['table'], $column)) {
                            $missing($alias, $pivot['table'], $column, 'in the link table');
                        }
                    }
                    break;
            }

            if ($type->morph() !== null && ($link['morph_class'] ?? null) === null) {
                $patch['morph_class'] = $morphClasses[$type->morph() === 'parent' ? $link['to'] : $alias];
            }

            if ($definition->isReference($alias)) {
                if (! $has($table, 'id')) {
                    $errors[] = "{$this->partName($part)}: the table {$table} has no id column, so its records can't be picked.";
                }

                if ($link['label_column'] === null) {
                    $patch['label_column'] = collect(self::LABEL_COLUMNS)->first(fn ($c) => $has($table, $c));
                } elseif (! $has($table, $link['label_column'])) {
                    $missing($alias, $table, $link['label_column'], 'to show records by');
                }
            }

            if ($patch !== []) {
                $patches[$alias] = ['link' => $patch];
            }
        }

        return [array_values(array_unique($errors)), $missingPivots, $patches];
    }

    /** How a part is named in messages: its label, else its alias in words ("Address"). */
    private function partName(array $part): string
    {
        return $part['label'] ?? Str::headline($part['alias']);
    }

    private function findEntity(array $part): ?Entity
    {
        $query = Entity::with('metas')->where('status', '<>', EntityStatus::DELETED);

        if ($part['entity_uid'] !== null) {
            return $query->where('uid', $part['entity_uid'])->first();
        }

        // Portable definitions name the entity by its table (or entity name).
        return $query->get()->first(fn (Entity $e) => self::tableOf($e) === $part['entity'])
            ?? Entity::with('metas')->where('status', '<>', EntityStatus::DELETED)->where('entity_name', $part['entity'])->first();
    }

    /** The entity's table: the published name, else table_name (same rule as the entity API). */
    public static function tableOf(Entity $entity): ?string
    {
        $metas = $entity->relationLoaded('metas') ? $entity->metas : $entity->metas()->get();
        $value = fn (string $key) => $metas->firstWhere('meta_key', $key)?->meta_value;

        return $value('published_table_name') ?: ($value('table_name') ?: null);
    }

    /** Field definitions keyed by name. */
    public static function mainFields(Entity $entity): array
    {
        $result = [];

        foreach (is_array($entity->fields) ? $entity->fields : [] as $key => $field) {
            $name = is_array($field) ? ($field['name'] ?? (is_string($key) ? $key : null)) : null;

            if ($name) {
                $result[$name] = $field + ['name' => $name];
            }
        }

        return $result;
    }

    /** Meta field definitions keyed by meta_key. */
    public static function metaFields(Entity $entity): array
    {
        $result = [];

        foreach (is_array($entity->meta_fields) ? $entity->meta_fields : [] as $field) {
            if (is_array($field) && ! empty($field['meta_key'])) {
                $result[$field['meta_key']] = $field;
            }
        }

        return $result;
    }
}
