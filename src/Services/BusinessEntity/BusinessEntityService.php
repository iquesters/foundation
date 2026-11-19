<?php

namespace Iquesters\Foundation\Services\BusinessEntity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Contracts\SchemaModels;
use Iquesters\Foundation\Events\BusinessEntityDefinitionChanged;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Services\BusinessEntitySchemaGenerator;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Saving business entity definitions, shared by the admin builder and
 * business-entity:sync. Approving a definition only validates it (every
 * table, column and pivot it uses must already exist), stores it and
 * (re)generates its form and table schemas; it never changes the database
 * structure.
 */
class BusinessEntityService
{
    use Loggable;

    public function __construct(
        private readonly BusinessEntityDefinitionValidator $validator,
        private readonly BusinessEntitySchemaGenerator $schemas,
    ) {}

    /** @throws InvalidArgumentException when the definition doesn't match the database */
    public function validate(array $definition): BusinessEntityDefinition
    {
        return $this->validator->validate($definition);
    }

    /** @param array{name: string, slug?: ?string, desc?: ?string, ref_module?: ?int} $attributes */
    public function create(array $attributes, array $definition): BusinessEntity
    {
        $parsed = $this->validate($definition);

        $businessEntity = DB::transaction(function () use ($attributes, $parsed) {
            $businessEntity = BusinessEntity::create([
                'uid' => (string) Str::ulid(),
                'ref_module' => $attributes['ref_module'] ?? null,
                'name' => $attributes['name'],
                'slug' => $this->uniqueSlug($attributes['slug'] ?? null ?: $attributes['name']),
                'desc' => $attributes['desc'] ?? null,
                'definition' => $parsed->toArray(),
                'status' => EntityStatus::ACTIVE,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncSchemas($businessEntity, $parsed);

            return $businessEntity;
        });

        static::logInfo('Business entity created', ['uid' => $businessEntity->uid, 'slug' => $businessEntity->slug]);
        BusinessEntityDefinitionChanged::dispatch($businessEntity, BusinessEntityDefinitionChanged::CREATED);

        return $businessEntity;
    }

    /** The slug never changes: it is in the API URLs and the schema slugs. */
    public function update(BusinessEntity $businessEntity, array $attributes, array $definition): BusinessEntity
    {
        $parsed = $this->validate($definition);

        DB::transaction(function () use ($businessEntity, $attributes, $parsed) {
            $businessEntity->update([
                'ref_module' => array_key_exists('ref_module', $attributes) ? $attributes['ref_module'] : $businessEntity->ref_module,
                'name' => $attributes['name'] ?? $businessEntity->name,
                'desc' => array_key_exists('desc', $attributes) ? $attributes['desc'] : $businessEntity->desc,
                'definition' => $parsed->toArray(),
                'status' => EntityStatus::ACTIVE,
                'updated_by' => auth()->id(),
            ]);

            $this->syncSchemas($businessEntity, $parsed);
        });

        static::logInfo('Business entity updated', ['uid' => $businessEntity->uid]);
        BusinessEntityDefinitionChanged::dispatch($businessEntity, BusinessEntityDefinitionChanged::UPDATED);

        return $businessEntity;
    }

    /** Soft-deletes the definition only; records in the part tables are not touched. */
    public function delete(BusinessEntity $businessEntity): void
    {
        $businessEntity->update(['status' => EntityStatus::DELETED, 'updated_by' => auth()->id()]);

        static::logInfo('Business entity deleted', ['uid' => $businessEntity->uid]);
        BusinessEntityDefinitionChanged::dispatch($businessEntity, BusinessEntityDefinitionChanged::DELETED);
    }

    /**
     * Create or update from a definition file ({name, slug, desc, definition}),
     * matched by slug. A deleted one with that slug is restored.
     *
     * @return array{0: BusinessEntity, 1: string} [business entity, "created"|"updated"]
     */
    public function upsertFromFile(array $file): array
    {
        $slug = is_string($file['slug'] ?? null) && $file['slug'] !== '' ? Str::slug($file['slug']) : Str::slug((string) ($file['name'] ?? ''));

        if (! is_string($file['name'] ?? null) || $file['name'] === '' || $slug === '') {
            throw new InvalidArgumentException('A definition file needs a "name" (and optionally a "slug").');
        }

        if (! is_array($file['definition'] ?? null)) {
            throw new InvalidArgumentException('A definition file needs a "definition" object.');
        }

        $attributes = ['name' => $file['name'], 'slug' => $slug, 'desc' => $file['desc'] ?? null];
        $existing = BusinessEntity::where('slug', $slug)->first();

        return $existing
            ? [$this->update($existing, $attributes, $file['definition']), BusinessEntityDefinitionChanged::UPDATED]
            : [$this->create($attributes, $file['definition']), BusinessEntityDefinitionChanged::CREATED];
    }

    /** A definition file for this business entity (entities named by table, so it works on any install). */
    public function toFile(BusinessEntity $businessEntity): array
    {
        return array_filter([
            'name' => $businessEntity->name,
            'slug' => $businessEntity->slug,
            'desc' => $businessEntity->desc,
            'definition' => $businessEntity->parsedDefinition()->toPortableArray(),
        ], fn ($v) => $v !== null);
    }

    public function syncSchemas(BusinessEntity $businessEntity, ?BusinessEntityDefinition $definition = null): void
    {
        // Schemas are stored by iquesters/user-interface; without it there is nothing to generate.
        if (app()->bound(SchemaModels::class)) {
            $this->schemas->sync($businessEntity, $definition);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business-entity';
        $slug = $base;

        for ($i = 2; BusinessEntity::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
