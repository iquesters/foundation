<?php

namespace Iquesters\Foundation\Support\BusinessEntity;

use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Models\Entity;

/**
 * Checks a business entity definition against the registered entities:
 * each part's entity exists and is published (its table exists), the
 * selected fields and meta keys are defined on it, and each link's foreign
 * key is a column of the right table. Each part's table is taken from its
 * entity, never from the submitted definition.
 */
final class BusinessEntityDefinitionValidator
{
    /** @throws InvalidArgumentException with every problem found */
    public function validate(array $data): BusinessEntityDefinition
    {
        $definition = BusinessEntityDefinition::fromArray($data);
        $errors = [];
        $tables = [];
        $entities = [];

        foreach ($definition->parts() as $alias => $part) {
            $entity = Entity::with('metas')
                ->where('uid', $part['entity_uid'])
                ->where('status', '<>', EntityStatus::DELETED)
                ->first();

            if (! $entity) {
                $errors[] = "Part \"{$alias}\": entity not found.";

                continue;
            }

            $table = self::tableOf($entity);

            if (! $table || ! Schema::hasTable($table)) {
                $errors[] = "Part \"{$alias}\": entity \"{$entity->entity_name}\" is not published yet (it has no table).";

                continue;
            }

            $tables[$alias] = $table;
            $entities[$alias] = $entity;

            $fieldNames = array_keys(self::mainFields($entity));
            $metaKeys = array_keys(self::metaFields($entity));

            foreach ($part['fields'] as $field) {
                if (! in_array($field['field'], $fieldNames, true)) {
                    $errors[] = "Part \"{$alias}\": \"{$field['field']}\" is not a field of {$entity->entity_name}.";
                }
            }

            foreach ($part['meta_fields'] as $field) {
                if (! in_array($field['meta_key'], $metaKeys, true)) {
                    $errors[] = "Part \"{$alias}\": \"{$field['meta_key']}\" is not a meta field of {$entity->entity_name}.";
                }
            }
        }

        if ($errors === []) {
            $definition = $definition->withTables($tables);

            foreach (array_keys($definition->parts()) as $alias) {
                foreach (array_keys($definition->foreignKeys($alias)) as $column) {
                    if (! Schema::hasColumn($tables[$alias], $column)) {
                        $errors[] = "Part \"{$alias}\": link column \"{$column}\" does not exist in table {$tables[$alias]}.";
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return $definition;
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
