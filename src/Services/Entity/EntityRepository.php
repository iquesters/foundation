<?php

namespace Iquesters\Foundation\Services\Entity;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Http\Query\DynamicEntityListQuery;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Data access for the generic entity API: list/find/create/update/soft-delete
 * on an entity table and its meta table. Callers must have run the allowlist
 * (EntitySchemaResolver::ensureEntityTableExists) and permission checks first.
 */
class EntityRepository
{
    use Loggable;

    public function __construct(
        private readonly EntitySchemaResolver $schema,
        private readonly EntityScope $scope,
        private readonly EntityValueNormalizer $normalizer,
    ) {}

    /** @return array{0: int, 1: Collection} [total count, page of records with meta] */
    public function paginate(string $table, array $filters, array $sorts, int $offset, int $length, ?string $search = null): array
    {
        [$main, $meta] = $this->schema->fieldDefinitions($table);

        $query = $this->visible($table);
        $this->scope->applyToQuery($query, $table);

        $listQuery = (new DynamicEntityListQuery($query, $table, $main, $meta, $this->schema->metaTable($table)))
            ->applyFilters($filters)
            ->applySearch($search)
            ->applySort($sorts);

        $total = $listQuery->count();

        return [$total, $this->attachMeta($table, $listQuery->paginate($offset, $length))];
    }

    public function find(string $table, string $reference): ?object
    {
        $record = $this->visible($table)
            ->where($this->schema->referenceColumn($table, $reference), $reference)
            ->first();

        if (! $record) {
            return null;
        }

        $this->scope->assertRecordInScope($table, $record);

        return $this->attachMeta($table, collect([$record]))->first();
    }

    public function idFor(string $table, string $reference): ?int
    {
        $id = $this->visible($table)->where($this->schema->referenceColumn($table, $reference), $reference)->value('id');

        return $id ? (int) $id : null;
    }

    public function create(string $table, array $mainData, array $metaData): object
    {
        $mainData = $this->withSystemDefaults($table, $mainData, false);

        $record = DB::transaction(function () use ($table, $mainData, $metaData) {
            $id = DB::table($table)->insertGetId($mainData);
            // Meta rows are written in the same transaction so partial writes don't survive failures.
            $this->insertMeta($table, $id, $metaData);

            return DB::table($table)->where('id', $id)->first();
        });

        return $this->attachMeta($table, collect([$record]))->first();
    }

    public function update(string $table, string $reference, array $mainData, array $metaData): object
    {
        unset($mainData['id'], $mainData['uid']);
        $mainData = $this->withSystemDefaults($table, $mainData, true);

        $record = DB::transaction(function () use ($table, $reference, $mainData, $metaData) {
            $existing = $this->lockedExisting($table, $reference);
            $mainData = $this->withMissingCreateAudit($mainData, $existing);

            if (! empty($mainData)) {
                DB::table($table)->where('id', $existing->id)->update($mainData);
            }

            $this->upsertMeta($table, (int) $existing->id, $metaData);

            return DB::table($table)->where('id', $existing->id)->first();
        });

        return $this->attachMeta($table, collect([$record]))->first();
    }

    public function softDelete(string $table, string $reference): object
    {
        if (! $this->schema->hasColumn($table, 'status')) {
            throw new \InvalidArgumentException("Table {$table} does not support soft delete because it has no status column.");
        }

        $record = DB::transaction(function () use ($table, $reference) {
            $existing = $this->lockedExisting($table, $reference);

            DB::table($table)->where('id', $existing->id)->update($this->softDeletePayload($table));

            return DB::table($table)->where('id', $existing->id)->first();
        });

        return $this->attachMeta($table, collect([$record]))->first();
    }

    public function attachMeta(string $table, Collection $records): Collection
    {
        $metaTable = $this->schema->metaTable($table);

        if (! $metaTable || $records->isEmpty()) {
            return $records;
        }

        $ids = $records->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return $records->map(function ($item) {
                $item->meta = [];

                return $item;
            });
        }

        $grouped = DB::table($metaTable)->whereIn('ref_parent', $ids)->get()->groupBy('ref_parent');

        return $records->map(function ($item) use ($grouped) {
            $item->meta = $grouped[$item->id] ?? [];

            return $item;
        });
    }

    /**
     * Base query that hides soft-deleted rows (status = 'deleted'), so they are
     * not listed and behave as "not found" for show/update/delete.
     */
    private function visible(string $table): Builder
    {
        $query = DB::table($table);

        if ($this->schema->hasColumn($table, 'status')) {
            $query->where(function ($q) use ($table) {
                $q->where("{$table}.status", '<>', EntityStatus::DELETED)->orWhereNull("{$table}.status");
            });
        }

        return $query;
    }

    private function lockedExisting(string $table, string $reference): object
    {
        $existing = $this->visible($table)
            ->where($this->schema->referenceColumn($table, $reference), $reference)
            ->first();

        if (! $existing) {
            throw new \RuntimeException('Record not found');
        }

        $this->scope->assertRecordInScope($table, $existing);

        return $existing;
    }

    private function withSystemDefaults(string $table, array $data, bool $isUpdate): array
    {
        $columns = $this->schema->columns($table);
        $userId = auth()->id();
        $now = now();

        $set = function (string $column, mixed $value) use (&$data, $columns) {
            if (in_array($column, $columns, true) && ! array_key_exists($column, $data)) {
                $data[$column] = $value;
            }
        };

        if (! $isUpdate) {
            if (in_array('uid', $columns, true) && empty($data['uid'])) {
                $data['uid'] = (string) Str::ulid();
            }
            $set('status', EntityStatus::ACTIVE);
            $set('created_by', $userId);
            $set('created_at', $now);
        }

        $set('updated_by', $userId);
        $set('updated_at', $now);

        return $data;
    }

    private function withMissingCreateAudit(array $data, object $existing): array
    {
        if (property_exists($existing, 'created_at') && empty($existing->created_at) && ! array_key_exists('created_at', $data)) {
            $data['created_at'] = now();
        }

        if (property_exists($existing, 'created_by') && empty($existing->created_by) && ! array_key_exists('created_by', $data)) {
            $data['created_by'] = auth()->id();
        }

        return $data;
    }

    private function softDeletePayload(string $table): array
    {
        $payload = ['status' => EntityStatus::DELETED];
        $userId = auth()->id();
        $now = now();

        foreach (['updated_by' => $userId, 'updated_at' => $now, 'deleted_by' => $userId, 'deleted_at' => $now] as $column => $value) {
            if ($this->schema->hasColumn($table, $column)) {
                $payload[$column] = $value;
            }
        }

        return $payload;
    }

    private function insertMeta(string $table, int $recordId, array $metaData): void
    {
        $metaTable = $this->schema->metaTable($table);

        if (! $metaTable || empty($metaData)) {
            return;
        }

        foreach ($metaData as $key => $value) {
            $row = ['ref_parent' => $recordId, 'meta_key' => $key, 'meta_value' => $this->normalizer->metaValue($value)];
            DB::table($metaTable)->insert($this->metaAuditColumns($metaTable, $row, true));
        }
    }

    private function upsertMeta(string $table, int $recordId, array $metaData): void
    {
        $metaTable = $this->schema->metaTable($table);

        if (! $metaTable || empty($metaData)) {
            return;
        }

        foreach ($metaData as $key => $value) {
            $exists = DB::table($metaTable)->where('ref_parent', $recordId)->where('meta_key', $key)->exists();

            DB::table($metaTable)->updateOrInsert(
                ['ref_parent' => $recordId, 'meta_key' => $key],
                $this->metaAuditColumns($metaTable, ['meta_value' => $this->normalizer->metaValue($value)], ! $exists)
            );
        }
    }

    private function metaAuditColumns(string $metaTable, array $row, bool $isNew): array
    {
        $userId = auth()->id();
        $now = now();
        $values = ['status' => EntityStatus::ACTIVE, 'updated_by' => $userId, 'updated_at' => $now];

        if ($isNew) {
            $values += ['created_by' => $userId, 'created_at' => $now];
        }

        foreach ($values as $column => $value) {
            if ($this->schema->hasColumn($metaTable, $column)) {
                $row[$column] = $value;
            }
        }

        return $row;
    }
}
