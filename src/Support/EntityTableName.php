<?php

namespace Iquesters\Foundation\Support;

/** Table names of entities: the base name from the entity name, and its published (plural) form. */
final class EntityTableName
{
    /** "Customer Address" → "customer_address". */
    public static function fromEntityName(string $entityName): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($entityName)), '_');
    }

    /** The published table name: "customer_address" → "customer_addresses", "category" → "categories". */
    public static function plural(string $tableName): string
    {
        foreach (['s', 'es', 'ies'] as $ending) {
            if (str_ends_with($tableName, $ending)) {
                return $tableName;
            }
        }

        if (str_ends_with($tableName, 'y')) {
            return substr($tableName, 0, -1).'ies';
        }

        if (in_array(substr($tableName, -1), ['x', 'z'], true) || in_array(substr($tableName, -2), ['sh', 'ch'], true)) {
            return $tableName.'es';
        }

        return $tableName.'s';
    }
}
