<?php

namespace Iquesters\Foundation\Http\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Iquesters\Foundation\Constants\EntityListParams;
use Iquesters\Foundation\System\Traits\Loggable;
use Throwable;

/**
 * @mixin Loggable
 */
class DynamicEntityListQuery
{
    use Loggable;

    private const SUPPORTED_OPERATORS = [
        EntityListParams::OP_EQ,
        EntityListParams::OP_NEQ,
        EntityListParams::OP_GT,
        EntityListParams::OP_GTE,
        EntityListParams::OP_LT,
        EntityListParams::OP_LTE,
        EntityListParams::OP_LIKE,
        EntityListParams::OP_STARTS,
        EntityListParams::OP_ENDS,
        EntityListParams::OP_IN,
    ];

    public function __construct(
        private Builder $query,
        private string $entity,
        private array $mainFieldDefinitions,
        private array $metaFieldDefinitions,
        private ?string $metaTable
    ) {}

    /**
     * @param  array<int, array{field: string, op: string, value: mixed}>  $filters
     */
    public function applyFilters(array $filters): static
    {
        try {
            foreach ($filters as $filter) {
                $field = $filter['field'] ?? null;
                $op = $filter['op'] ?? EntityListParams::OP_EQ;
                $value = $filter['value'] ?? null;

                if (! is_string($field) || $value === null || $value === '') {
                    continue;
                }

                if (! in_array($op, self::SUPPORTED_OPERATORS, true)) {
                    $this->logDebug(sprintf(
                        'DynamicEntityListQuery@applyFilters skipped unsupported operator | entity=%s | field=%s | op=%s',
                        $this->entity,
                        $field,
                        $op
                    ));

                    continue;
                }

                if (array_key_exists($field, $this->mainFieldDefinitions)) {
                    $this->applyMainColumnFilter($field, $op, $value);
                } elseif ($this->metaTable && array_key_exists($field, $this->metaFieldDefinitions)) {
                    $this->applyMetaFilter($field, $op, $value);
                } else {
                    $this->logDebug(sprintf(
                        'DynamicEntityListQuery@applyFilters skipped unknown field | entity=%s | field=%s',
                        $this->entity,
                        $field
                    ));
                }
            }

            return $this;
        } catch (Throwable $e) {
            $this->logError(sprintf(
                'DynamicEntityListQuery@applyFilters failed | entity=%s | error=%s',
                $this->entity,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * Free-text search: a row matches when any text-like main column or any
     * of its meta values contains the term (case-insensitive under the
     * default collations). LIKE wildcards in the term are matched literally.
     */
    public function applySearch(?string $term): static
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $this;
        }

        $term = mb_substr($term, 0, EntityListParams::MAX_SEARCH_LENGTH);
        $like = '%'.addcslashes($term, '\\%_').'%';

        $columns = array_keys(array_filter(
            $this->mainFieldDefinitions,
            fn (array $definition) => $this->isSearchableType($definition['type'] ?? 'string')
        ));
        $metaTable = $this->metaTable;

        if (empty($columns) && ! $metaTable) {
            return $this;
        }

        $this->query->where(function (Builder $where) use ($columns, $metaTable, $like) {
            foreach ($columns as $column) {
                $where->orWhere($column, 'LIKE', $like);
            }

            if ($metaTable) {
                $where->orWhereIn('id', function (Builder $sub) use ($metaTable, $like) {
                    $sub->select('ref_parent')->from($metaTable)->where('meta_value', 'LIKE', $like);
                });
            }
        });

        return $this;
    }

    /**
     * @param  array<int, array{column: string, direction: string}>  $sorts
     */
    public function applySort(array $sorts): static
    {
        try {
            foreach ($sorts as $sort) {
                $column = $sort['column'] ?? null;
                $direction = $sort['direction'] ?? EntityListParams::DIR_ASC;

                if (! is_string($column) || $column === '') {
                    continue;
                }

                $this->applySingleSort($column, $direction);
            }

            return $this;
        } catch (Throwable $e) {
            $this->logError(sprintf(
                'DynamicEntityListQuery@applySort failed | entity=%s | error=%s',
                $this->entity,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    public function count(): int
    {
        try {
            return $this->query->count();
        } catch (Throwable $e) {
            $this->logError(sprintf(
                'DynamicEntityListQuery@count failed | entity=%s | error=%s',
                $this->entity,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    public function paginate(int $offset, int $length): Collection
    {
        try {
            return $this->query->offset($offset)->limit($length)->get();
        } catch (Throwable $e) {
            $this->logError(sprintf(
                'DynamicEntityListQuery@paginate failed | entity=%s | offset=%d | length=%d | error=%s',
                $this->entity,
                $offset,
                $length,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    private function applySingleSort(string $column, string $direction): void
    {
        $direction = strtolower($direction) === EntityListParams::DIR_DESC
            ? EntityListParams::DIR_DESC
            : EntityListParams::DIR_ASC;

        if (array_key_exists($column, $this->mainFieldDefinitions)) {
            $this->query->orderBy($column, $direction);

            return;
        }

        if ($this->metaTable && array_key_exists($column, $this->metaFieldDefinitions)) {
            $type = $this->metaFieldDefinitions[$column]['type'] ?? 'string';

            if ($this->isStringSortableType($type)) {
                $this->applyMetaSort($column, $direction);
            } else {
                $this->logWarning(sprintf(
                    'DynamicEntityListQuery@applySort skipped non-string meta sort | entity=%s | column=%s | type=%s',
                    $this->entity,
                    $column,
                    $type
                ));
            }

            return;
        }

        $this->logDebug(sprintf(
            'DynamicEntityListQuery@applySort skipped unknown column | entity=%s | column=%s',
            $this->entity,
            $column
        ));
    }

    private function applyMainColumnFilter(string $field, string $op, mixed $value): void
    {
        $type = $this->mainFieldDefinitions[$field]['type'] ?? 'string';

        if ($op === EntityListParams::OP_IN) {
            $values = array_map(
                fn ($v) => $this->castFilterValue(trim($v), $type),
                explode(',', (string) $value)
            );
            $this->query->whereIn($field, $values);

            return;
        }

        if (in_array($op, [EntityListParams::OP_LIKE, EntityListParams::OP_STARTS, EntityListParams::OP_ENDS], true)) {
            $this->query->where($field, 'LIKE', $this->buildLikeValue($op, $value));

            return;
        }

        $this->query->where($field, $this->mapOperator($op), $this->castFilterValue($value, $type));
    }

    private function applyMetaFilter(string $field, string $op, mixed $value): void
    {
        $metaTable = $this->metaTable;

        $this->query->whereIn('id', function (Builder $sub) use ($metaTable, $field, $op, $value) {
            $sub->select('ref_parent')->from($metaTable)->where('meta_key', $field);

            if ($op === EntityListParams::OP_IN) {
                $values = array_map(fn ($v) => trim($v), explode(',', (string) $value));
                $sub->whereIn('meta_value', $values);

                return;
            }

            if (in_array($op, [EntityListParams::OP_LIKE, EntityListParams::OP_STARTS, EntityListParams::OP_ENDS], true)) {
                $sub->where('meta_value', 'LIKE', $this->buildLikeValue($op, $value));

                return;
            }

            $sub->where('meta_value', $this->mapOperator($op), (string) $value);
        });
    }

    private function applyMetaSort(string $column, string $direction): void
    {
        $this->query->orderByRaw(
            "(SELECT `meta_value` FROM `{$this->metaTable}` WHERE `ref_parent` = `{$this->entity}`.`id` AND `meta_key` = ? LIMIT 1) {$direction}",
            [$column]
        );
    }

    private function buildLikeValue(string $op, mixed $value): string
    {
        return match ($op) {
            EntityListParams::OP_STARTS => $value.'%',
            EntityListParams::OP_ENDS => '%'.$value,
            default => '%'.$value.'%',
        };
    }

    private function mapOperator(string $op): string
    {
        return match ($op) {
            EntityListParams::OP_NEQ => '!=',
            EntityListParams::OP_GT => '>',
            EntityListParams::OP_GTE => '>=',
            EntityListParams::OP_LT => '<',
            EntityListParams::OP_LTE => '<=',
            default => '=',
        };
    }

    private function isSearchableType(string $type): bool
    {
        return in_array(strtolower($type), ['string', 'varchar', 'text', 'char', 'longtext', 'mediumtext', 'tinytext', 'email'], true);
    }

    private function isStringSortableType(string $type): bool
    {
        return in_array(strtolower($type), ['string', 'varchar', 'text', 'char'], true);
    }

    private function castFilterValue(mixed $value, string $fieldType): mixed
    {
        return match (strtolower($fieldType)) {
            'integer', 'bigint', 'mediumint', 'smallint', 'tinyint' => is_numeric($value) ? (int) $value : $value,
            'decimal', 'float', 'double', 'real' => is_numeric($value) ? (float) $value : $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            default => $value,
        };
    }
}
