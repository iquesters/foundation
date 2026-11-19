<?php

namespace Iquesters\Foundation\Services\BusinessEntity;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Services\Entity\EntityPresenter;
use Iquesters\Foundation\Services\Entity\EntityRepository;
use Iquesters\Foundation\Services\Entity\EntitySchemaResolver;
use Iquesters\Foundation\Services\Entity\EntityScope;
use Iquesters\Foundation\Services\Entity\EntityValidator;
use Iquesters\Foundation\Services\Entity\EntityValueNormalizer;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
use Iquesters\Foundation\Support\EntityPermission;

/**
 * Records of a business entity: one root row plus its linked parts, read and
 * written as a single record through the single-table entity services (same
 * allowlist, validation, normalization, scope and audit columns).
 *
 * Payload and response use flat keys "{alias}__{field}"; meta fields go in
 * "meta" ({"customer__phone": "…"} in, [{meta_key, meta_value}] out), as with
 * the entity API, so the form engine works unchanged.
 *
 * - Writes run in one transaction, in BusinessEntityDefinition::writeOrder();
 *   link columns are filled in from the related part's id.
 * - A non-root part is optional: it is only created when one of its fields is sent.
 * - Every action needs the matching entity.{table}.{action} permission on each
 *   part's table (admins always pass).
 * - Delete soft-deletes the root and the parts it owns (has_one chains);
 *   belongs_to parts are shared and stay.
 */
class BusinessEntityRecords
{
    public function __construct(
        private readonly EntitySchemaResolver $schema,
        private readonly EntityRepository $repository,
        private readonly EntityValidator $validator,
        private readonly EntityValueNormalizer $normalizer,
        private readonly EntityScope $scope,
        private readonly EntityPresenter $presenter,
    ) {}

    /**
     * The active business entity and its definition; every part's table must
     * still be a registered entity table of the same entity.
     *
     * @return array{0: BusinessEntity, 1: BusinessEntityDefinition}
     */
    public function resolve(string $slug): array
    {
        $businessEntity = BusinessEntity::where('slug', $slug)->where('status', EntityStatus::ACTIVE)->first();

        if (! $businessEntity) {
            throw new \RuntimeException("Business entity {$slug} not found");
        }

        try {
            $definition = $businessEntity->parsedDefinition();

            foreach ($definition->parts() as $part) {
                if ($this->schema->ensureEntityTableExists($part['table'])->uid !== $part['entity_uid']) {
                    throw new \RuntimeException('Part table belongs to another entity');
                }
            }
        } catch (\InvalidArgumentException|\RuntimeException) {
            // A broken definition is a configuration problem; don't reveal which table failed.
            throw new \RuntimeException("Business entity {$slug} not found");
        }

        return [$businessEntity, $definition];
    }

    /** Requires entity.{table}.{action} on every part's table. */
    public function authorize(BusinessEntityDefinition $definition, string $action): void
    {
        foreach ($definition->tables() as $table) {
            if (! EntityPermission::allows(auth()->user(), $table, $action)) {
                throw new AuthorizationException("You do not have permission to {$action} this record.");
            }
        }
    }

    /** @return array{0: int, 1: Collection} [total, page of records] */
    public function paginate(BusinessEntityDefinition $definition, array $filters, array $sorts, int $offset, int $length, ?string $search): array
    {
        $root = $definition->root();
        $rootKeys = $this->rootColumnKeys($definition);

        // Filters and sort apply to the root table: "customer__name" or a root system column ("status").
        $translate = function (string $key) use ($rootKeys) {
            if (! array_key_exists($key, $rootKeys)) {
                throw new \InvalidArgumentException("Cannot filter or sort by {$key}: only the root part's fields are supported.");
            }

            return $rootKeys[$key];
        };

        $filters = array_map(fn ($f) => ['field' => $translate($f['field'])] + $f, $filters);
        $sorts = array_map(fn ($s) => ['column' => $translate($s['column'])] + $s, $sorts);

        [$total, $records] = $this->repository->paginate($root['table'], $filters, $sorts, $offset, $length, $search);

        return [$total, $this->present($definition, $this->withParts($definition, $records))];
    }

    public function find(BusinessEntityDefinition $definition, string $uid): ?object
    {
        $root = $this->repository->find($definition->root()['table'], $uid);

        return $root ? $this->present($definition, $this->withParts($definition, collect([$root])))->first() : null;
    }

    public function create(BusinessEntityDefinition $definition, array $payload, array $meta): object
    {
        $input = $this->split($definition, $payload, $meta);
        $rootAlias = $definition->rootAlias();

        if (! in_array(true, array_column($input, 'sent'), true)) {
            throw new \InvalidArgumentException('Request payload is empty.');
        }

        // The root is always created; other parts only when something was sent for them.
        $writes = array_filter($input, fn ($part, $alias) => $alias === $rootAlias || $part['sent'], ARRAY_FILTER_USE_BOTH);
        $data = $this->validatedParts($definition, $writes, []);

        $uid = DB::transaction(function () use ($definition, $data, $rootAlias) {
            $created = [];

            foreach ($definition->writeOrder() as $alias) {
                if (! isset($data[$alias])) {
                    continue;
                }

                [$main, $metaData] = $data[$alias];
                $main += $this->linkValues($definition, $alias, $created);
                $created[$alias] = $this->repository->create($definition->part($alias)['table'], $main, $metaData);
            }

            return $this->reference($created[$rootAlias]);
        });

        return $this->find($definition, $uid);
    }

    public function update(BusinessEntityDefinition $definition, string $uid, array $payload, array $meta): object
    {
        $current = $this->currentParts($definition, $uid);
        $input = $this->split($definition, $payload, $meta);
        $writes = array_filter($input, fn ($part) => $part['sent']);

        // Existing parts get a partial update; a missing part sent now is created (full rules).
        $existing = array_filter($current, fn ($record) => $record !== null);
        $data = $this->validatedParts($definition, $writes, $existing);

        foreach (array_keys($data) as $alias) {
            if (! isset($existing[$alias]) && ! EntityPermission::allows(auth()->user(), $definition->part($alias)['table'], 'create')) {
                throw new AuthorizationException('You do not have permission to create this record.');
            }
        }

        DB::transaction(function () use ($definition, $data, $current) {
            $created = [];

            foreach ($definition->writeOrder() as $alias) {
                $table = $definition->part($alias)['table'];
                [$main, $metaData] = $data[$alias] ?? [[], []];
                // Link columns pointing at parts created in this request.
                $main += $this->linkValues($definition, $alias, $created);

                if ($current[$alias] !== null) {
                    if ($main !== [] || $metaData !== []) {
                        $this->repository->update($table, $this->reference($current[$alias]), $main, $metaData);
                    }
                } elseif (isset($data[$alias])) {
                    $main += $this->linkValues($definition, $alias, $current);
                    $created[$alias] = $this->repository->create($table, $main, $metaData);
                }
            }
        });

        return $this->find($definition, $uid);
    }

    public function delete(BusinessEntityDefinition $definition, string $uid): object
    {
        $current = $this->currentParts($definition, $uid);
        $record = $this->find($definition, $uid);

        DB::transaction(function () use ($definition, $current) {
            foreach (array_reverse($definition->writeOrder()) as $alias) {
                $table = $definition->part($alias)['table'];

                $isRoot = $alias === $definition->rootAlias();

                // The root always (fails without a status column, like the entity API); owned parts when they can be.
                if ($current[$alias] !== null && $this->owned($definition, $alias) && ($isRoot || $this->schema->hasColumn($table, 'status'))) {
                    $this->repository->softDelete($table, $this->reference($current[$alias]));
                }
            }
        });

        $record->status = EntityStatus::DELETED;

        return $record;
    }

    /* ---------------------------------------------------------------- */

    /**
     * Payload per part: main columns and meta values sent for its selected
     * fields ("sent" = at least one key present).
     *
     * @return array<string, array{main: array, meta: array, sent: bool}>
     */
    private function split(BusinessEntityDefinition $definition, array $payload, array $meta): array
    {
        $meta = $this->normalizer->flattenMeta($meta);
        $parts = array_map(fn () => ['main' => [], 'meta' => [], 'sent' => false], $definition->parts());

        foreach ($definition->fields() as $field) {
            $source = $field['meta'] ? $meta : $payload;

            if (array_key_exists($field['key'], $source)) {
                $parts[$field['alias']][$field['meta'] ? 'meta' : 'main'][$field['name']] = $source[$field['key']];
                $parts[$field['alias']]['sent'] = true;
            }
        }

        return $parts;
    }

    /**
     * Validate and normalize each part with its entity's rules (selected
     * fields only). Errors from all parts are reported together, keyed by
     * "{alias}__{field}" (meta: "meta.{alias}__{key}").
     *
     * @param  array<string, object>  $existing  current records of parts that exist (update)
     * @return array<string, array{0: array, 1: array}> alias => [main data, meta data]
     */
    private function validatedParts(BusinessEntityDefinition $definition, array $writes, array $existing): array
    {
        $errors = [];
        $data = [];

        foreach ($writes as $alias => $input) {
            $part = $definition->part($alias);
            $table = $part['table'];
            $isUpdate = isset($existing[$alias]);
            [$mainDefs, $metaDefs] = $this->schema->fieldDefinitions($table);

            $selectedMain = array_column($part['fields'], 'field');
            $selectedMeta = array_column($part['meta_fields'], 'meta_key');
            $mainDefs = array_intersect_key($mainDefs, array_flip($selectedMain));
            $metaDefs = array_intersect_key($metaDefs, array_flip($selectedMeta));
            $writable = $this->schema->writableColumns($table, $mainDefs);

            try {
                $this->validator->validate(
                    $table,
                    $mainDefs,
                    $metaDefs,
                    array_intersect_key($input['main'], array_flip($writable)),
                    $input['meta'],
                    $writable,
                    $isUpdate,
                    $isUpdate ? (int) $existing[$alias]->id : null
                );

                $main = $this->normalizer->mainData($input['main'], $mainDefs, $writable);
                $metaData = $this->normalizer->metaData($metaDefs, $input['meta']);
                $this->scope->assertWritePayloadInScope($main);
                $data[$alias] = [$main, $metaData];
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    $errors[$this->errorKey($alias, $key)] = $messages;
                }
            } catch (\InvalidArgumentException $e) {
                // Normalizer: a value that can't be converted to its column type.
                throw new \InvalidArgumentException("{$alias}: ".$e->getMessage(), 0, $e);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function errorKey(string $alias, string $key): string
    {
        return str_starts_with($key, 'meta.')
            ? 'meta.'.BusinessEntityDefinition::fieldKey($alias, substr($key, 5))
            : BusinessEntityDefinition::fieldKey($alias, $key);
    }

    /**
     * Link columns of this part that point at parts in $records (by id).
     *
     * @param  array<string, object|null>  $records
     */
    private function linkValues(BusinessEntityDefinition $definition, string $alias, array $records): array
    {
        $values = [];

        foreach ($definition->foreignKeys($alias) as $column => $other) {
            if (isset($records[$other])) {
                $values[$column] = (int) $records[$other]->id;
            }
        }

        return $values;
    }

    /**
     * The current record of each part for one root uid (null when missing).
     *
     * @return array<string, object|null>
     */
    private function currentParts(BusinessEntityDefinition $definition, string $uid): array
    {
        $root = $this->repository->find($definition->root()['table'], $uid);

        if (! $root) {
            throw new \RuntimeException('Record not found');
        }

        $row = $this->withParts($definition, collect([$root]))->first();

        return array_map(fn ($alias) => $row->__parts[$alias] ?? null, array_combine(array_keys($definition->parts()), array_keys($definition->parts())));
    }

    /**
     * Load the linked parts for root records (one query per part), in
     * definition order (a part's "to" part is always loaded before it).
     * Each root record gets __parts: alias => record|null.
     */
    private function withParts(BusinessEntityDefinition $definition, Collection $roots): Collection
    {
        $rootAlias = $definition->rootAlias();
        $roots->each(function ($record) use ($rootAlias) {
            $record->__parts = [$rootAlias => $record];
        });

        foreach ($definition->parts() as $alias => $part) {
            if ($part['link'] === null) {
                continue;
            }

            ['type' => $type, 'to' => $to, 'foreign_key' => $fk] = $part['link'];
            $parents = $roots->map(fn ($r) => $r->__parts[$to] ?? null)->filter();

            $lookup = $type === BusinessEntityDefinition::HAS_ONE
                ? ['column' => $fk, 'values' => $parents->pluck('id')]
                : ['column' => 'id', 'values' => $parents->pluck($fk)];

            $values = $lookup['values']->filter(fn ($v) => $v !== null && $v !== '')->unique()->values();
            $rows = $values->isEmpty() ? collect() : $this->visibleRows($part['table'], $lookup['column'], $values->all());
            // has_one: the first (oldest) row per parent.
            $byKey = $rows->sortBy('id')->groupBy(fn ($row) => (string) $row->{$lookup['column']})->map->first();

            $roots->each(function ($record) use ($alias, $to, $type, $fk, $byKey) {
                $parent = $record->__parts[$to] ?? null;
                $key = $parent ? (string) ($type === BusinessEntityDefinition::HAS_ONE ? $parent->id : ($parent->{$fk} ?? '')) : '';
                $parts = $record->__parts;
                $parts[$alias] = $key !== '' ? ($byKey[$key] ?? null) : null;
                $record->__parts = $parts;
            });
        }

        return $roots;
    }

    /** Rows of a part table (not soft-deleted, within scope), with meta. */
    private function visibleRows(string $table, string $column, array $values): Collection
    {
        $query = DB::table($table)->whereIn($column, $values);

        if ($this->schema->hasColumn($table, 'status')) {
            $query->where(fn ($q) => $q->where('status', '<>', EntityStatus::DELETED)->orWhereNull('status'));
        }

        $this->scope->applyToQuery($query, $table);

        return $this->repository->attachMeta($table, $query->get());
    }

    /**
     * Flat records: the root's own columns (id, uid, status, timestamps,
     * detail_summary), every selected field as "{alias}__{field}", selected
     * meta as meta[{meta_key: "{alias}__{key}", meta_value}], and parts:
     * {alias: {id, uid} | null}.
     */
    private function present(BusinessEntityDefinition $definition, Collection $records): Collection
    {
        $roots = $this->presenter->many($records->map(function ($record) {
            $root = clone $record;
            unset($root->__parts);

            return $root;
        }))->values();

        return $records->values()->map(function ($record, $i) use ($definition, $roots) {
            $root = $roots[$i];
            $out = (object) array_intersect_key((array) $root, array_flip(['id', 'uid', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'detail_summary']));
            $meta = [];

            foreach ($definition->fields() as $field) {
                $part = $record->__parts[$field['alias']] ?? null;

                if ($field['meta']) {
                    $item = collect($part->meta ?? [])->firstWhere('meta_key', $field['name']);
                    $value = $item->meta_value ?? null;
                    $meta[] = (object) ['meta_key' => $field['key'], 'meta_value' => $this->presenter->isSensitive($field['name']) ? '***REDACTED***' : $value];
                } else {
                    $out->{$field['key']} = $this->presenter->isSensitive($field['name']) ? null : ($part->{$field['name']} ?? null);
                }
            }

            $out->meta = $meta;
            $out->parts = array_map(fn ($part) => $part ? ['id' => $part->id, 'uid' => $part->uid ?? null] : null, $record->__parts);

            return $out;
        });
    }

    /** Root columns usable in filters/sort: "{root}__{field}" for selected fields, plus system columns. */
    private function rootColumnKeys(BusinessEntityDefinition $definition): array
    {
        $alias = $definition->rootAlias();
        $keys = [];

        foreach ($definition->root()['fields'] as $field) {
            $keys[BusinessEntityDefinition::fieldKey($alias, $field['field'])] = $field['field'];
        }

        foreach (['id', 'uid', 'status', 'created_at', 'updated_at'] as $column) {
            if ($this->schema->hasColumn($definition->root()['table'], $column)) {
                $keys[$column] = $column;
            }
        }

        return $keys;
    }

    /** The parts the record owns: the root and has_one chains from it. */
    private function owned(BusinessEntityDefinition $definition, string $alias): bool
    {
        $link = $definition->part($alias)['link'];

        return $link === null || ($link['type'] === BusinessEntityDefinition::HAS_ONE && $this->owned($definition, $link['to']));
    }

    /** What the entity repository uses to find a row: uid when the table has one, else id. */
    private function reference(object $record): string
    {
        return isset($record->uid) && $record->uid !== '' ? (string) $record->uid : (string) $record->id;
    }
}
