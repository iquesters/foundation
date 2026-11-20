<?php

namespace Iquesters\Foundation\Services\Schema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Contracts\SchemaModels;

/**
 * Stores generated form and table schemas (the models are bound by
 * iquesters/user-interface through SchemaModels). One place for "find the
 * schema this resource generated before, then update it or create it", used
 * for entities (EntitySchemaService) and business entities
 * (BusinessEntitySchemaGenerator).
 */
class GeneratedSchemaStore
{
    public const FORM = 'form';

    public const TABLE = 'table';

    /** Whether schemas can be stored at all (iquesters/user-interface installed). */
    public function available(): bool
    {
        return app()->bound(SchemaModels::class);
    }

    /**
     * Update the existing schema or create it.
     *
     * @param  string  $kind  self::FORM or self::TABLE
     * @param  array{uid?: ?string, slugs?: string[], entity_keys?: string[]}  $find  tried in this order: uid, slugs, schema "entity" values
     * @param  array{name: string, description: string, schema: array}  $attributes  written on create and update (extra keys, e.g. slug or status, too)
     * @param  array  $createOnly  only written when the schema is created (e.g. the slug of a schema that keeps its slug)
     */
    public function save(string $kind, array $find, array $attributes, array $createOnly = []): Model
    {
        $record = $this->find($kind, $find);

        if ($record) {
            $record->update($attributes + ['updated_by' => auth()->id()]);

            return $record;
        }

        $model = $this->model($kind);

        return $model::create($attributes + $createOnly + [
            'uid' => (string) Str::ulid(),
            'extra_info' => [],
            'status' => EntityStatus::ACTIVE,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function find(string $kind, array $find): ?Model
    {
        $model = $this->model($kind);

        if (! empty($find['uid']) && ($record = $model::where('uid', $find['uid'])->first())) {
            return $record;
        }

        foreach ($find['slugs'] ?? [] as $slug) {
            if ($record = $model::where('slug', $slug)->first()) {
                return $record;
            }
        }

        foreach ($find['entity_keys'] ?? [] as $entityKey) {
            if ($record = $model::where('schema->entity', $entityKey)->first()) {
                return $record;
            }
        }

        return null;
    }

    /** @return class-string<Model> */
    private function model(string $kind): string
    {
        $models = app(SchemaModels::class);

        return $kind === self::FORM ? $models->formSchemaModel() : $models->tableSchemaModel();
    }
}
