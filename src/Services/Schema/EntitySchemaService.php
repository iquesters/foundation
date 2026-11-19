<?php

namespace Iquesters\Foundation\Services\Schema;

use Illuminate\Support\Str;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Services\BusinessEntitySchemaGenerator;
use Iquesters\Foundation\Services\FormSchemaGenerator;
use Iquesters\Foundation\Services\TableSchemaGenerator;
use Iquesters\Foundation\Support\EntityTableName;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * The generated form and table schema of an entity: generate them
 * (FormSchemaGenerator / TableSchemaGenerator), store them
 * (GeneratedSchemaStore) and remember their uids in the entity's metas
 * (form_schema_uid, table_schema_uid). A schema generated before is found
 * again by uid, then by slug ("{slug}-form"), then by its "entity" value,
 * and updated in place.
 */
class EntitySchemaService
{
    use Loggable;

    public function __construct(
        private readonly FormSchemaGenerator $forms,
        private readonly TableSchemaGenerator $tables,
        private readonly GeneratedSchemaStore $store,
    ) {}

    /**
     * Both schemas (the table schema opens the form schema).
     *
     * @param  array  $customFields  the entity's fields without the system ones
     * @return array{form_schema_uid: string, table_schema_uid: string}
     */
    public function sync(Entity $entity, array $customFields, array $metaFields): array
    {
        $formUid = $this->syncFormSchema($entity, $customFields, $metaFields);

        return ['form_schema_uid' => $formUid, 'table_schema_uid' => $this->syncTableSchema($entity, $customFields, $formUid)];
    }

    /** @return string the form schema uid */
    public function syncFormSchema(Entity $entity, array $customFields, array $metaFields): string
    {
        return $this->save($entity, GeneratedSchemaStore::FORM, 'Form', $this->forms->generate($entity, $customFields, $metaFields));
    }

    /** @return string the table schema uid */
    public function syncTableSchema(Entity $entity, array $customFields, string $formSchemaUid): string
    {
        return $this->save($entity, GeneratedSchemaStore::TABLE, 'Table', $this->tables->generate($entity, $customFields, $formSchemaUid));
    }

    /** Create or update one entity meta. */
    public function setMeta(Entity $entity, string $key, mixed $value): void
    {
        $exists = $entity->metas()->where('meta_key', $key)->exists();

        $entity->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value, 'status' => 'active', 'updated_by' => auth()->id()] + ($exists ? [] : ['created_by' => auth()->id()])
        );
    }

    private function save(Entity $entity, string $kind, string $label, array $schema): string
    {
        $metaKey = "{$kind}_schema_uid";
        $previousUid = $entity->getMeta($metaKey);
        $slug = $this->slugOf($entity);

        $record = $this->store->save($kind, [
            'uid' => $previousUid,
            'slugs' => $this->slugCandidates($slug, $kind),
            'entity_keys' => $this->tableNameCandidates($entity),
        ], [
            'name' => "{$entity->entity_name} {$label}",
            'description' => "Auto-generated {$kind} for {$entity->entity_name}",
            'schema' => $schema,
        ], ['slug' => "{$slug}-{$kind}"]);

        if ($record->uid !== $previousUid) {
            $this->setMeta($entity, $metaKey, $record->uid);
            static::logInfo("Entity {$kind} schema stored", ['entity_uid' => $entity->uid, 'previous_uid' => $previousUid, 'uid' => $record->uid]);
        }

        if ($kind === GeneratedSchemaStore::TABLE) {
            $this->keepBusinessEntityForms($record->slug);
        }

        return $record->uid;
    }

    /**
     * A regenerated table goes back to the entity's own form; a business
     * entity that uses this table ("Use the form in") keeps its form there.
     */
    private function keepBusinessEntityForms(string $tableSlug): void
    {
        $users = BusinessEntity::active()
            ->whereHas('metas', fn ($q) => $q->where('meta_key', 'use_in_table')->where('meta_value', $tableSlug))
            ->get();

        foreach ($users as $businessEntity) {
            if (BusinessEntitySchemaGenerator::outputOf($businessEntity) !== BusinessEntitySchemaGenerator::OUTPUT_API) {
                app(BusinessEntitySchemaGenerator::class)->useFormInTable($businessEntity, $tableSlug);
            }
        }
    }

    /** The entity's slug (one is derived and saved for older entities without one). */
    private function slugOf(Entity $entity): string
    {
        $slug = trim((string) ($entity->slug ?? ''));

        if ($slug === '') {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($entity->entity_name)), '-') ?: 'entity-'.$entity->uid;
            $entity->slug = $slug;
            $entity->save();
        }

        return $slug;
    }

    /** "{slug}-form", also for the singular and plural slug (older schemas). */
    private function slugCandidates(string $slug, string $kind): array
    {
        $candidates = [];

        foreach ([$slug, Str::singular($slug), Str::plural($slug)] as $base) {
            $base = trim((string) $base, '-');

            if ($base !== '') {
                $candidates[] = "{$base}-{$kind}";
            }
        }

        return array_values(array_unique($candidates));
    }

    /** Table names an older schema may carry as its "entity" value. */
    private function tableNameCandidates(Entity $entity): array
    {
        $candidates = [];

        foreach ([trim((string) ($entity->getMeta('table_name') ?? '')), EntityTableName::fromEntityName($entity->entity_name)] as $base) {
            if ($base !== '') {
                array_push($candidates, $base, EntityTableName::plural($base), Str::singular($base));
            }
        }

        return array_values(array_unique($candidates));
    }
}
