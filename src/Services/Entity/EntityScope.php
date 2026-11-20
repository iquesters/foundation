<?php

namespace Iquesters\Foundation\Services\Entity;

/**
 * Row-level access scoping (e.g. multi-tenant organisation_id).
 *
 * Any table with a scoped column is restricted to the IDs an EntityScopeResolver
 * (tagged 'entity-scope-resolvers') resolves for the current actor. If no
 * resolver claims the column, access fails closed rather than exposing every row.
 */
class EntityScope
{
    public const SCOPED_COLUMNS = ['organisation_id'];

    public function __construct(private readonly EntitySchemaResolver $schema) {}

    /** Restrict a query to the scope-column values the current actor may access. */
    public function applyToQuery($query, string $table): void
    {
        foreach (array_intersect(self::SCOPED_COLUMNS, $this->schema->columns($table)) as $column) {
            $query->whereIn($column, $this->allowedIds($column));
        }
    }

    /**
     * Deny access to an out-of-scope record with the same 'Record not found'
     * error used for missing rows, so the two are indistinguishable.
     */
    public function assertRecordInScope(string $table, object $record): void
    {
        foreach (array_intersect(self::SCOPED_COLUMNS, $this->schema->columns($table)) as $column) {
            $value = $record->{$column} ?? null;

            if ($value === null || ! in_array((int) $value, $this->allowedIds($column), true)) {
                throw new \RuntimeException('Record not found');
            }
        }
    }

    /** Reject a payload that targets a scope value the actor may not write. */
    public function assertWritePayloadInScope(array $mainData): void
    {
        foreach (self::SCOPED_COLUMNS as $column) {
            if (! array_key_exists($column, $mainData)) {
                continue;
            }

            if (! in_array((int) $mainData[$column], $this->allowedIds($column), true)) {
                throw new \InvalidArgumentException("You do not have access to the specified {$column}.");
            }
        }
    }

    /** @return array<int> */
    public function allowedIds(string $column): array
    {
        foreach (app()->tagged('entity-scope-resolvers') as $resolver) {
            $allowed = $resolver->resolveAllowedIds($column);

            if ($allowed !== null) {
                return $allowed;
            }
        }

        throw new \RuntimeException("No access-scope resolver is registered for the '{$column}' column.");
    }
}
