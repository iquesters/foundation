<?php

namespace Iquesters\Foundation\Services\BusinessEntity;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Events\BusinessEntityRecordSaved;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Services\Entity\EntityPresenter;
use Iquesters\Foundation\Services\Entity\EntityRepository;
use Iquesters\Foundation\Services\Entity\EntitySchemaResolver;
use Iquesters\Foundation\Services\Entity\EntityScope;
use Iquesters\Foundation\Services\Entity\EntityValidator;
use Iquesters\Foundation\Services\Entity\EntityValueNormalizer;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationType;
use Iquesters\Foundation\Support\EntityPermission;

/**
 * Records of a business entity: one root row plus its linked parts, read and
 * written as a single record through the single-table entity services (same
 * allowlist, validation, normalization, scope and audit columns). Data stays
 * in the parts' own tables, link columns and pivot tables.
 *
 * Payload and response (see BusinessEntityDefinition):
 * - tree parts (root, has_one, belongs_to, morph_one): flat "{alias}__{field}";
 *   meta in "meta" ({"customer__phone": "…"} in, [{meta_key, meta_value}] out);
 * - lists of rows (has_many, morph_many): "{alias}": [{uid?, field, meta: {}}];
 *   on update the list replaces the current rows (matched by uid, else id):
 *   new rows are created, missing ones deleted;
 * - references: "{alias}": a uid (or id), or a list of them (pivots are synced).
 * A key that is not sent leaves that part unchanged.
 *
 * - Writes run in one transaction: tree parts in BusinessEntityDefinition::writeOrder(),
 *   then lists and pivots. Link columns and morph types are filled in.
 * - A non-root tree part is optional: it is only created when one of its
 *   fields (or a list or reference hanging from it) is sent.
 * - Permissions: entity.{table}.{action} on every table the record writes,
 *   and entity.{table}.view on referenced tables (admins always pass).
 * - Delete soft-deletes the root and what it owns (has_one chains and their
 *   lists of rows); belongs_to parts and references are shared and stay.
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
     * The active business entity and its definition; every entity part's
     * table must still be a registered entity table of the same entity.
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
                if ($part['model'] !== null) {
                    if (! Schema::hasTable($part['table'])) {
                        throw new \RuntimeException('Model table missing');
                    }
                } elseif ($this->schema->ensureEntityTableExists($part['table'])->uid !== $part['entity_uid']) {
                    throw new \RuntimeException('Part table belongs to another entity');
                }
            }
        } catch (\InvalidArgumentException|\RuntimeException) {
            // A broken definition is a configuration problem; don't reveal which table failed.
            throw new \RuntimeException("Business entity {$slug} not found");
        }

        return [$businessEntity, $definition];
    }

    /** Requires entity.{table}.{action} on every table the record writes. */
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

        return [$total, $this->present($definition, $this->load($definition, $records))];
    }

    public function find(BusinessEntityDefinition $definition, string $uid): ?object
    {
        $root = $this->repository->find($definition->root()['table'], $uid);

        return $root ? $this->present($definition, $this->load($definition, collect([$root])))->first() : null;
    }

    public function create(BusinessEntityDefinition $definition, array $payload, array $meta): object
    {
        $input = $this->split($definition, $payload, $meta);
        $rootAlias = $definition->rootAlias();

        if (! in_array(true, array_column($input, 'sent'), true)) {
            throw new \InvalidArgumentException('Request payload is empty.');
        }

        $this->markParentsSent($definition, $input);

        // The root is always created; other tree parts only when something was sent for them.
        $writes = array_filter($input, fn ($part, $alias) => $definition->isTree($alias) && ($alias === $rootAlias || $part['sent']), ARRAY_FILTER_USE_BOTH);
        [$data, $leaves] = $this->validated($definition, $writes, $input, [], []);

        $uid = DB::transaction(function () use ($definition, $data, $leaves, $rootAlias) {
            $created = $this->writeTree($definition, $data, array_fill_keys($definition->treeAliases(), null), $leaves);
            $this->writeLeaves($definition, $leaves, $created, []);

            return $this->reference($created[$rootAlias]);
        });

        $record = $this->find($definition, $uid);
        BusinessEntityRecordSaved::dispatch($definition, $record, 'created');

        return $record;
    }

    public function update(BusinessEntityDefinition $definition, string $uid, array $payload, array $meta): object
    {
        $loaded = $this->loadOne($definition, $uid);
        $current = $loaded->__parts;
        $input = $this->split($definition, $payload, $meta);
        $this->markParentsSent($definition, $input);

        $writes = array_filter($input, fn ($part, $alias) => $definition->isTree($alias) && $part['sent'], ARRAY_FILTER_USE_BOTH);

        // Existing parts get a partial update; a missing part sent now is created (full rules).
        $existing = array_filter($current, fn ($record) => $record !== null);
        [$data, $leaves] = $this->validated($definition, $writes, $input, $existing, $loaded->__leaves);

        foreach (array_keys($data) as $alias) {
            if (! isset($existing[$alias]) && ! EntityPermission::allows(auth()->user(), $definition->part($alias)['table'], 'create')) {
                throw new AuthorizationException('You do not have permission to create this record.');
            }
        }

        DB::transaction(function () use ($definition, $data, $leaves, $current, $loaded) {
            $records = $this->writeTree($definition, $data, $current, $leaves);
            $this->writeLeaves($definition, $leaves, $records, $loaded->__leaves);
        });

        $record = $this->find($definition, $uid);
        BusinessEntityRecordSaved::dispatch($definition, $record, 'updated');

        return $record;
    }

    public function delete(BusinessEntityDefinition $definition, string $uid): object
    {
        $loaded = $this->loadOne($definition, $uid);
        $current = $loaded->__parts;
        $record = $this->find($definition, $uid);

        DB::transaction(function () use ($definition, $current, $loaded) {
            // Owned lists of rows first, then owned tree parts (children before parents).
            foreach ($definition->leafAliases() as $alias) {
                if ($definition->isRows($alias) && $this->owned($definition, $definition->part($alias)['link']['to'])) {
                    foreach ($loaded->__leaves[$alias] ?? [] as $row) {
                        $this->removeRow($definition->part($alias)['table'], $row);
                    }
                }
            }

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
        BusinessEntityRecordSaved::dispatch($definition, $record, 'deleted');

        return $record;
    }

    /**
     * Choices for a reference part: [{value, label}] of the rows it can
     * point at (visible, in scope), searched on the label column.
     */
    public function options(BusinessEntityDefinition $definition, string $alias, ?string $search, int $limit = 100): array
    {
        if (! isset($definition->parts()[$alias]) || ! $definition->isReference($alias)) {
            throw new \InvalidArgumentException("{$alias} is not a reference of this business entity.");
        }

        $part = $definition->part($alias);
        $table = $part['table'];
        $this->authorizeTable($table, 'view');

        $valueColumn = $this->valueColumn($table);
        $labelColumn = $part['link']['label_column'] ?? $valueColumn;
        $query = $this->visibleQuery($table);

        if ($search !== null && $search !== '') {
            $query->where($labelColumn, 'like', '%'.addcslashes(mb_substr($search, 0, 100), '%_\\').'%');
        }

        return $query->orderBy($labelColumn)->limit(max(1, min($limit, 200)))
            ->get(array_unique(['id', $valueColumn, $labelColumn]))
            ->map(fn ($row) => ['value' => (string) $row->{$valueColumn}, 'label' => (string) ($row->{$labelColumn} ?? $row->{$valueColumn})])
            ->all();
    }

    /* ---------------------------------------------------------------- */
    /* Input */
    /* ---------------------------------------------------------------- */

    /**
     * Payload per part ("sent" = at least one of its keys present):
     * tree parts get main columns and meta values, leaves their raw value.
     *
     * @return array<string, array{main: array, meta: array, value: mixed, sent: bool}>
     */
    private function split(BusinessEntityDefinition $definition, array $payload, array $meta): array
    {
        $meta = $this->normalizer->flattenMeta($meta);
        $parts = array_map(fn () => ['main' => [], 'meta' => [], 'value' => null, 'sent' => false], $definition->parts());

        foreach ($definition->fields() as $field) {
            $source = $field['meta'] ? $meta : $payload;

            if (array_key_exists($field['key'], $source)) {
                $parts[$field['alias']][$field['meta'] ? 'meta' : 'main'][$field['name']] = $source[$field['key']];
                $parts[$field['alias']]['sent'] = true;
            }
        }

        foreach ($definition->leafAliases() as $alias) {
            if (array_key_exists($alias, $payload)) {
                $parts[$alias]['value'] = $payload[$alias];
                $parts[$alias]['sent'] = true;
            }
        }

        return $parts;
    }

    /** A sent part needs the tree part it hangs from (a belongs_to compose part is the exception: it is the parent). */
    private function markParentsSent(BusinessEntityDefinition $definition, array &$input): void
    {
        foreach (array_reverse(array_keys($definition->parts())) as $alias) {
            $link = $definition->part($alias)['link'];

            if (! $input[$alias]['sent'] || $link === null) {
                continue;
            }

            if (! ($definition->isTree($alias) && $definition->type($alias)->holder() === RelationType::HOLDER_PARENT)) {
                $input[$link['to']]['sent'] = true;
            }
        }
    }

    /**
     * Validate and normalize everything before writing: each tree part with
     * its entity's rules (selected fields only), each list row the same way,
     * and each reference against the rows it may point at. Errors from all
     * parts are reported together, keyed "{alias}__{field}" (meta:
     * "meta.{alias}__{key}"); list and reference errors under "{alias}".
     *
     * @param  array<string, object>  $existing  current records of tree parts that exist (update)
     * @param  array<string, mixed>  $currentLeaves  current leaf values (update)
     * @return array{0: array<string, array{0: array, 1: array}>, 1: array<string, mixed>} [tree data, leaf data]
     */
    private function validated(BusinessEntityDefinition $definition, array $writes, array $input, array $existing, array $currentLeaves): array
    {
        $errors = [];
        $data = [];
        $leaves = [];

        foreach ($writes as $alias => $part) {
            try {
                $data[$alias] = $this->validatedRow($definition->part($alias), $part['main'], $part['meta'], $existing[$alias] ?? null);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    $errors[$this->errorKey($alias, $key)] = $messages;
                }
            }
        }

        foreach ($definition->leafAliases() as $alias) {
            if (! $input[$alias]['sent']) {
                continue;
            }

            try {
                $leaves[$alias] = $definition->isRows($alias)
                    ? $this->validatedRows($definition->part($alias), $input[$alias]['value'], $currentLeaves[$alias] ?? [])
                    : $this->resolvedReference($definition, $alias, $input[$alias]['value']);
            } catch (ValidationException $e) {
                $errors[$alias] = array_merge(...array_values($e->errors()));
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$data, $leaves];
    }

    /** @return array{0: array, 1: array} [main data, meta data] */
    private function validatedRow(array $part, array $main, array $meta, ?object $existing): array
    {
        $table = $part['table'];
        [$mainDefs, $metaDefs] = $this->schema->fieldDefinitions($table);

        $mainDefs = array_intersect_key($mainDefs, array_flip(array_column($part['fields'], 'field')));
        $metaDefs = array_intersect_key($metaDefs, array_flip(array_column($part['meta_fields'], 'meta_key')));
        $writable = $this->schema->writableColumns($table, $mainDefs);
        $isUpdate = $existing !== null;

        $this->validator->validate(
            $table,
            $mainDefs,
            $metaDefs,
            array_intersect_key($main, array_flip($writable)),
            $meta,
            $writable,
            $isUpdate,
            $isUpdate ? (int) $existing->id : null
        );

        try {
            $mainData = $this->normalizer->mainData($main, $mainDefs, $writable);
            $metaData = $this->normalizer->metaData($metaDefs, $meta);
        } catch (\InvalidArgumentException $e) {
            // Normalizer: a value that can't be converted to its column type.
            throw new \InvalidArgumentException("{$part['alias']}: ".$e->getMessage(), 0, $e);
        }

        $this->scope->assertWritePayloadInScope($mainData);

        return [$mainData, $metaData];
    }

    /**
     * A list of rows: each validated with its entity's rules (an existing row,
     * matched by uid or id, gets a partial update).
     *
     * @param  list<object>  $currentRows
     * @return list<array{existing: ?object, main: array, meta: array}>
     */
    private function validatedRows(array $part, mixed $value, array $currentRows): array
    {
        $value ??= [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages([$part['alias'] => ['Send a list of rows.']]);
        }

        $byReference = [];
        foreach ($currentRows as $row) {
            $byReference[(string) ($row->uid ?? '')] = $row;
            $byReference[(string) $row->id] = $row;
        }
        unset($byReference['']);

        $fieldNames = array_column($part['fields'], 'field');
        $metaKeys = array_column($part['meta_fields'], 'meta_key');
        $errors = [];
        $rows = [];

        foreach ($value as $i => $row) {
            if (! is_array($row)) {
                $errors[] = 'Row '.($i + 1).': must be an object.';

                continue;
            }

            $reference = (string) ($row['uid'] ?? $row['id'] ?? '');
            $existing = $reference !== '' ? ($byReference[$reference] ?? null) : null;

            if ($reference !== '' && $existing === null) {
                $errors[] = 'Row '.($i + 1).': it is not one of this record\'s rows.';

                continue;
            }

            $main = array_intersect_key($row, array_flip($fieldNames));
            $meta = array_intersect_key(is_array($row['meta'] ?? null) ? $row['meta'] : [], array_flip($metaKeys));

            try {
                [$mainData, $metaData] = $this->validatedRow($part, $main, $meta, $existing);
                $rows[] = ['existing' => $existing, 'main' => $mainData, 'meta' => $metaData];
            } catch (ValidationException $e) {
                foreach (array_merge(...array_values($e->errors())) as $message) {
                    $errors[] = 'Row '.($i + 1).': '.$message;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([$part['alias'] => $errors]);
        }

        return $rows;
    }

    /**
     * A reference's picked value(s) as row ids: each must be a row the user
     * can see (entity.{table}.view, not deleted, in scope).
     *
     * @return int|null|list<int>
     */
    private function resolvedReference(BusinessEntityDefinition $definition, string $alias, mixed $value): int|array|null
    {
        $table = $definition->part($alias)['table'];
        $many = $definition->type($alias)->cardinality() === RelationType::MANY;
        $values = $many ? ($value ?? []) : [$value];

        if (! is_array($values) || ($many && ! array_is_list($values))) {
            throw ValidationException::withMessages([$alias => ['Send a list of values.']]);
        }

        $values = array_values(array_unique(array_filter(array_map(
            fn ($v) => is_scalar($v) ? trim((string) $v) : '',
            $values
        ), fn ($v) => $v !== '')));

        if ($values === []) {
            return $many ? [] : null;
        }

        $this->authorizeTable($table, 'view');
        $valueColumn = $this->valueColumn($table);
        $ids = $this->visibleQuery($table)->whereIn($valueColumn, $values)->pluck('id', $valueColumn);

        $unknown = array_diff($values, array_map('strval', $ids->keys()->all()));
        if ($unknown !== []) {
            throw ValidationException::withMessages([$alias => ['The selected value was not found: '.implode(', ', $unknown).'.']]);
        }

        $ids = array_map('intval', array_values($ids->all()));

        return $many ? $ids : $ids[0];
    }

    private function errorKey(string $alias, string $key): string
    {
        return str_starts_with($key, 'meta.')
            ? 'meta.'.BusinessEntityDefinition::fieldKey($alias, substr($key, 5))
            : BusinessEntityDefinition::fieldKey($alias, $key);
    }

    /* ---------------------------------------------------------------- */
    /* Writing */
    /* ---------------------------------------------------------------- */

    /**
     * Tree parts in write order: create the missing ones that have data,
     * update the existing ones, with link columns, morph types and
     * belongs_to references filled in.
     *
     * @param  array<string, object|null>  $current
     * @return array<string, object|null> the tree records after writing
     */
    private function writeTree(BusinessEntityDefinition $definition, array $data, array $current, array $leaves): array
    {
        $records = $current;
        $references = $this->parentReferenceColumns($definition, $leaves);

        foreach ($definition->writeOrder() as $alias) {
            $table = $definition->part($alias)['table'];
            $hasData = isset($data[$alias]);
            [$main, $metaData] = $data[$alias] ?? [[], []];
            $main = ($references[$alias] ?? []) + $main;

            if ($current[$alias] !== null) {
                // Link columns pointing at parts created in this request.
                $main += $this->linkValues($definition, $alias, array_diff_key($records, array_filter($current)));

                if ($main !== [] || $metaData !== []) {
                    $records[$alias] = $this->repository->update($table, $this->reference($current[$alias]), $main, $metaData);
                }
            } elseif ($hasData) {
                $main += $this->linkValues($definition, $alias, $records) + $definition->morphColumns($alias);
                $records[$alias] = $this->repository->create($table, $main, $metaData);
            }
        }

        return $records;
    }

    /**
     * Lists of rows and pivots, once the tree records exist.
     *
     * @param  array<string, object|null>  $records  tree records
     * @param  array<string, mixed>  $currentLeaves
     */
    private function writeLeaves(BusinessEntityDefinition $definition, array $leaves, array $records, array $currentLeaves): void
    {
        foreach ($leaves as $alias => $value) {
            $part = $definition->part($alias);
            $link = $part['link'];
            $type = $definition->type($alias);
            $parent = $records[$link['to']] ?? null;

            if ($parent === null || $type->holder() === RelationType::HOLDER_PARENT) {
                continue; // belongs_to references were written with their parent.
            }

            if ($type->holder() === RelationType::HOLDER_PIVOT) {
                $this->syncPivot($link, (int) $parent->id, $value);

                continue;
            }

            // A list of rows: the sent list replaces the current one.
            $keep = [];
            $fixed = [$link['foreign_key'] => (int) $parent->id] + $definition->morphColumns($alias);

            foreach ($value as $row) {
                if ($row['existing']) {
                    $keep[] = (int) $row['existing']->id;
                    if ($row['main'] !== [] || $row['meta'] !== []) {
                        $this->repository->update($part['table'], $this->reference($row['existing']), $row['main'], $row['meta']);
                    }
                } else {
                    $this->repository->create($part['table'], $fixed + $row['main'], $row['meta']);
                }
            }

            foreach ($currentLeaves[$alias] ?? [] as $row) {
                if (! in_array((int) $row->id, $keep, true)) {
                    $this->removeRow($part['table'], $row);
                }
            }
        }
    }

    /** belongs_to references: [tree alias => [column => id|null]] for the part that holds the column. */
    private function parentReferenceColumns(BusinessEntityDefinition $definition, array $leaves): array
    {
        $columns = [];

        foreach ($leaves as $alias => $value) {
            if ($definition->isReference($alias) && $definition->type($alias)->holder() === RelationType::HOLDER_PARENT) {
                $link = $definition->part($alias)['link'];
                $columns[$link['to']][$link['foreign_key']] = $value;
            }
        }

        return $columns;
    }

    /** Make the pivot rows of one parent exactly $relatedIds (keeps the existing ones). */
    private function syncPivot(array $link, int $parentId, array $relatedIds): void
    {
        $pivot = $link['pivot'];
        $query = fn () => DB::table($pivot['table'])
            ->where($pivot['foreign_pivot_key'], $parentId)
            ->when(isset($pivot['morph_type']), fn ($q) => $q->where($pivot['morph_type'], $link['morph_class']));

        $current = $query()->pluck($pivot['related_pivot_key'])->map(fn ($id) => (int) $id)->all();

        $removed = array_diff($current, $relatedIds);
        if ($removed !== []) {
            $query()->whereIn($pivot['related_pivot_key'], $removed)->delete();
        }

        $now = now();
        $rows = [];
        foreach (array_diff($relatedIds, $current) as $id) {
            $row = [$pivot['foreign_pivot_key'] => $parentId, $pivot['related_pivot_key'] => $id];

            if (isset($pivot['morph_type'])) {
                $row[$pivot['morph_type']] = $link['morph_class'];
            }

            foreach (['created_at', 'updated_at'] as $column) {
                if ($this->schema->hasColumn($pivot['table'], $column)) {
                    $row[$column] = $now;
                }
            }

            $rows[] = $row;
        }

        if ($rows !== []) {
            DB::table($pivot['table'])->insert($rows);
        }
    }

    /** A row removed from a list: soft-deleted when its table has a status column, else deleted. */
    private function removeRow(string $table, object $row): void
    {
        if ($this->schema->hasColumn($table, 'status')) {
            $this->repository->softDelete($table, $this->reference($row));
        } else {
            DB::table($table)->where('id', $row->id)->delete();
        }
    }

    /**
     * Link columns of this tree part that point at parts in $records (by id).
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

    /* ---------------------------------------------------------------- */
    /* Reading */
    /* ---------------------------------------------------------------- */

    private function loadOne(BusinessEntityDefinition $definition, string $uid): object
    {
        $root = $this->repository->find($definition->root()['table'], $uid);

        if (! $root) {
            throw new \RuntimeException('Record not found');
        }

        return $this->load($definition, collect([$root]))->first();
    }

    /**
     * Load the linked parts for root records (one query per part), in
     * definition order (a part's "to" part is always loaded before it).
     * Each root record gets __parts (tree alias => record|null) and
     * __leaves (alias => list of rows | id | list of ids).
     */
    private function load(BusinessEntityDefinition $definition, Collection $roots): Collection
    {
        $rootAlias = $definition->rootAlias();
        $roots->each(function ($record) use ($rootAlias) {
            $record->__parts = [$rootAlias => $record];
            $record->__leaves = [];
        });

        foreach ($definition->parts() as $alias => $part) {
            $link = $part['link'];

            if ($link === null) {
                continue;
            }

            $type = $definition->type($alias);
            $parents = $roots->map(fn ($r) => $r->__parts[$link['to']] ?? null)->filter();

            if ($definition->isTree($alias)) {
                $this->loadTreePart($definition, $alias, $roots, $parents);
            } elseif ($type->holder() === RelationType::HOLDER_PIVOT) {
                $this->loadPivot($alias, $link, $roots, $parents);
            } elseif ($type->holder() === RelationType::HOLDER_PARENT) {
                $roots->each(function ($record) use ($alias, $link) {
                    $parent = $record->__parts[$link['to']] ?? null;
                    $id = $parent->{$link['foreign_key']} ?? null;
                    $record->__leaves = $record->__leaves + [$alias => $id !== null && $id !== '' ? (int) $id : null];
                });
            } else {
                $this->loadRows($definition, $alias, $roots, $parents);
            }
        }

        return $roots;
    }

    private function loadTreePart(BusinessEntityDefinition $definition, string $alias, Collection $roots, Collection $parents): void
    {
        $part = $definition->part($alias);
        ['to' => $to, 'foreign_key' => $fk] = $part['link'];
        $ownsKey = $definition->type($alias)->holder() === RelationType::HOLDER_SELF;

        $lookup = $ownsKey
            ? ['column' => $fk, 'values' => $parents->pluck('id')]
            : ['column' => 'id', 'values' => $parents->pluck($fk)];

        $values = $lookup['values']->filter(fn ($v) => $v !== null && $v !== '')->unique()->values();
        $rows = $values->isEmpty() ? collect() : $this->visibleRows($part['table'], $lookup['column'], $values->all(), $definition->morphColumns($alias));
        // has_one: the first (oldest) row per parent.
        $byKey = $rows->sortBy('id')->groupBy(fn ($row) => (string) $row->{$lookup['column']})->map->first();

        $roots->each(function ($record) use ($alias, $to, $ownsKey, $fk, $byKey) {
            $parent = $record->__parts[$to] ?? null;
            $key = $parent ? (string) ($ownsKey ? $parent->id : ($parent->{$fk} ?? '')) : '';
            $parts = $record->__parts;
            $parts[$alias] = $key !== '' ? ($byKey[$key] ?? null) : null;
            $record->__parts = $parts;
        });
    }

    private function loadRows(BusinessEntityDefinition $definition, string $alias, Collection $roots, Collection $parents): void
    {
        $part = $definition->part($alias);
        $link = $part['link'];
        $ids = $parents->pluck('id')->unique()->values()->all();
        $grouped = $ids === []
            ? collect()
            : $this->visibleRows($part['table'], $link['foreign_key'], $ids, $definition->morphColumns($alias))
                ->sortBy('id')->groupBy(fn ($row) => (string) $row->{$link['foreign_key']});

        $roots->each(function ($record) use ($alias, $link, $grouped) {
            $parent = $record->__parts[$link['to']] ?? null;
            $record->__leaves = $record->__leaves + [$alias => $parent ? ($grouped[(string) $parent->id] ?? collect())->values()->all() : []];
        });
    }

    private function loadPivot(string $alias, array $link, Collection $roots, Collection $parents): void
    {
        $pivot = $link['pivot'];
        $ids = $parents->pluck('id')->unique()->values()->all();
        $grouped = $ids === []
            ? collect()
            : DB::table($pivot['table'])
                ->whereIn($pivot['foreign_pivot_key'], $ids)
                ->when(isset($pivot['morph_type']), fn ($q) => $q->where($pivot['morph_type'], $link['morph_class']))
                ->get([$pivot['foreign_pivot_key'], $pivot['related_pivot_key']])
                ->groupBy(fn ($row) => (string) $row->{$pivot['foreign_pivot_key']});

        $roots->each(function ($record) use ($alias, $link, $pivot, $grouped) {
            $parent = $record->__parts[$link['to']] ?? null;
            $related = $parent ? ($grouped[(string) $parent->id] ?? collect())->pluck($pivot['related_pivot_key'])->map(fn ($id) => (int) $id)->sort()->values()->all() : [];
            $record->__leaves = $record->__leaves + [$alias => $related];
        });
    }

    /** Rows of a part table (not soft-deleted, within scope, morph type matching), with meta. */
    private function visibleRows(string $table, string $column, array $values, array $morphColumns = []): Collection
    {
        $query = $this->visibleQuery($table)->whereIn($column, $values);

        foreach ($morphColumns as $morphColumn => $class) {
            $query->where($morphColumn, $class);
        }

        return $this->repository->attachMeta($table, $query->get());
    }

    private function visibleQuery(string $table): Builder
    {
        $query = DB::table($table);

        if ($this->schema->hasColumn($table, 'status')) {
            $query->where(fn ($q) => $q->where('status', '<>', EntityStatus::DELETED)->orWhereNull('status'));
        }

        $this->scope->applyToQuery($query, $table);

        return $query;
    }

    /**
     * Flat records: the root's own columns (id, uid, status, timestamps,
     * detail_summary), every selected tree field as "{alias}__{field}",
     * selected meta as meta[{meta_key: "{alias}__{key}", meta_value}], each
     * leaf under its alias, and parts: {alias: {id, uid} | null}.
     */
    private function present(BusinessEntityDefinition $definition, Collection $records): Collection
    {
        $roots = $this->presenter->many($records->map(function ($record) {
            $root = clone $record;
            unset($root->__parts, $root->__leaves);

            return $root;
        }))->values();

        $referenceValues = $this->referenceValues($definition, $records);

        return $records->values()->map(function ($record, $i) use ($definition, $roots, $referenceValues) {
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

            foreach ($definition->leafAliases() as $alias) {
                $value = $record->__leaves[$alias] ?? null;

                if ($definition->isRows($alias)) {
                    $out->{$alias} = array_map(fn ($row) => $this->presentRow($definition->part($alias), $row), $value ?? []);
                } elseif (is_array($value)) {
                    $out->{$alias} = array_values(array_filter(array_map(fn ($id) => $referenceValues[$alias][$id] ?? null, $value), fn ($v) => $v !== null));
                } else {
                    $out->{$alias} = $value !== null ? ($referenceValues[$alias][$value] ?? null) : null;
                }
            }

            $out->meta = $meta;
            $out->parts = array_map(fn ($part) => $part ? ['id' => $part->id, 'uid' => $part->uid ?? null] : null, $record->__parts);

            return $out;
        });
    }

    /** One row of a list: {id, uid, selected fields, meta: {key: value}}. */
    private function presentRow(array $part, object $row): array
    {
        $out = ['id' => $row->id, 'uid' => $row->uid ?? null];

        foreach ($part['fields'] as $field) {
            $name = $field['field'];
            $out[$name] = $this->presenter->isSensitive($name) ? null : ($row->{$name} ?? null);
        }

        $meta = [];
        foreach ($part['meta_fields'] as $field) {
            $key = $field['meta_key'];
            $item = collect($row->meta ?? [])->firstWhere('meta_key', $key);
            $meta[$key] = $this->presenter->isSensitive($key) ? '***REDACTED***' : ($item->meta_value ?? null);
        }

        $out['meta'] = (object) $meta;

        return $out;
    }

    /**
     * Referenced ids to the value the API uses (uid when the table has one).
     *
     * @return array<string, array<int, string>> alias => [id => value]
     */
    private function referenceValues(BusinessEntityDefinition $definition, Collection $records): array
    {
        $values = [];

        foreach ($definition->leafAliases() as $alias) {
            if (! $definition->isReference($alias)) {
                continue;
            }

            $table = $definition->part($alias)['table'];
            $ids = $records->flatMap(fn ($r) => (array) ($r->__leaves[$alias] ?? []))->filter()->unique()->values()->all();
            $valueColumn = $this->valueColumn($table);

            $values[$alias] = $ids === [] ? [] : ($valueColumn === 'id'
                ? array_combine($ids, array_map('strval', $ids))
                : DB::table($table)->whereIn('id', $ids)->pluck($valueColumn, 'id')->map(fn ($v) => (string) $v)->all());
        }

        return $values;
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

    /** The parts the record owns: the root and has_one / morph_one chains from it. */
    private function owned(BusinessEntityDefinition $definition, string $alias): bool
    {
        $link = $definition->part($alias)['link'];

        return $link === null || ($definition->isTree($alias)
            && $definition->type($alias)->holder() === RelationType::HOLDER_SELF
            && $this->owned($definition, $link['to']));
    }

    private function authorizeTable(string $table, string $action): void
    {
        if (! EntityPermission::allows(auth()->user(), $table, $action)) {
            throw new AuthorizationException("You do not have permission to {$action} {$table}.");
        }
    }

    /** The column references are sent and returned as: uid when the table has one, else id. */
    private function valueColumn(string $table): string
    {
        return $this->schema->hasColumn($table, 'uid') ? 'uid' : 'id';
    }

    /** What the entity repository uses to find a row: uid when the table has one, else id. */
    private function reference(object $record): string
    {
        return isset($record->uid) && $record->uid !== '' ? (string) $record->uid : (string) $record->id;
    }
}
