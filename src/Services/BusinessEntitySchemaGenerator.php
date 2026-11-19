<?php

namespace Iquesters\Foundation\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Iquesters\Foundation\Constants\Constant as FormSchemaConstant;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Contracts\SchemaModels;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;

/**
 * The form and table schema of a business entity: one form over all its
 * parts (field ids "{alias}__{field}"), talking to the business entity API
 * (/api/foundation/v1/business-entity/...), and a table listing its
 * records. Stored through SchemaModels (bound by iquesters/user-interface);
 * the uids are kept in the business entity's metas.
 */
class BusinessEntitySchemaGenerator
{
    public const API_PREFIX = '/api/foundation/v1/business-entity';

    public function __construct(
        private readonly FormSchemaGenerator $forms,
        private readonly TableSchemaGenerator $tables,
    ) {}

    /** Schema "entity" value; the UI table lists it from the business entity API. */
    public static function entityKey(BusinessEntity $businessEntity): string
    {
        return 'business-entity/'.$businessEntity->slug;
    }

    public static function formSlug(BusinessEntity $businessEntity): string
    {
        return 'business-'.$businessEntity->slug.'-form';
    }

    public static function tableSlug(BusinessEntity $businessEntity): string
    {
        return 'business-'.$businessEntity->slug.'-table';
    }

    /** Form schema array (not stored). */
    public function formSchema(BusinessEntity $businessEntity, BusinessEntityDefinition $definition): array
    {
        [$fields, $metaFields] = $this->schemaFields($definition);
        $base = url(self::API_PREFIX);
        $slug = $businessEntity->slug;

        $schema = $this->forms->build(Str::headline($businessEntity->name), self::entityKey($businessEntity), [
            FormSchemaConstant::MODE_CREATE => "{$base}/store/{$slug}",
            FormSchemaConstant::MODE_VIEW => "{$base}/show/{$slug}/{uid}",
            FormSchemaConstant::MODE_EDIT => "{$base}/update/{$slug}/{uid}",
            FormSchemaConstant::MODE_DELETE => "{$base}/delete/{$slug}/{uid}",
        ], $fields, $metaFields);

        // The record comes from the business entity API, not the single-table entity API.
        $schema['dataEndpoint'] = "{$base}/show/{$slug}/{uid}";

        return $schema;
    }

    /** Table schema array (not stored). */
    public function tableSchema(BusinessEntity $businessEntity, BusinessEntityDefinition $definition, string $formSchemaUid): array
    {
        [$fields] = $this->schemaFields($definition);

        $schema = $this->tables->build(self::entityKey($businessEntity), $fields, $formSchemaUid);
        // List page "Create" button: opens the form in create mode.
        $schema['create'] = ['form' => self::formSlug($businessEntity), 'label' => 'Create '.Str::headline($businessEntity->name)];

        return $schema;
    }

    /**
     * Create or update both stored schemas and remember their uids.
     *
     * @return array{form_schema_uid: string, table_schema_uid: string}
     */
    public function sync(BusinessEntity $businessEntity, ?BusinessEntityDefinition $definition = null): array
    {
        $definition ??= $businessEntity->parsedDefinition();
        $models = app(SchemaModels::class);
        $name = $businessEntity->name;

        $form = $this->upsert(
            $models->formSchemaModel(),
            $businessEntity->getMeta('form_schema_uid'),
            self::formSlug($businessEntity),
            "{$name} Form",
            "Auto-generated form for the {$name} business entity",
            $this->formSchema($businessEntity, $definition)
        );

        $table = $this->upsert(
            $models->tableSchemaModel(),
            $businessEntity->getMeta('table_schema_uid'),
            self::tableSlug($businessEntity),
            "{$name} Table",
            "Auto-generated table for the {$name} business entity",
            $this->tableSchema($businessEntity, $definition, $form->uid)
        );

        $businessEntity->setMeta('form_schema_uid', $form->uid);
        $businessEntity->setMeta('table_schema_uid', $table->uid);

        return ['form_schema_uid' => $form->uid, 'table_schema_uid' => $table->uid];
    }

    /**
     * Field definitions for the generators, keyed "{alias}__{field}", with
     * labels prefixed by the part's label when there is more than one part.
     *
     * @return array{0: array, 1: array} [main fields, meta fields]
     */
    public function schemaFields(BusinessEntityDefinition $definition): array
    {
        $entities = Entity::whereIn('uid', array_column($definition->parts(), 'entity_uid'))->get()->keyBy('uid');
        $multiPart = count($definition->parts()) > 1;
        $fields = [];
        $metaFields = [];

        foreach ($definition->fields() as $selected) {
            $part = $definition->part($selected['alias']);
            $entity = $entities[$part['entity_uid']] ?? null;
            $definitions = $entity
                ? ($selected['meta'] ? BusinessEntityDefinitionValidator::metaFields($entity) : BusinessEntityDefinitionValidator::mainFields($entity))
                : [];
            $source = $definitions[$selected['name']] ?? [];

            $label = $selected['label'] ?? ($source['label'] ?? Str::headline($selected['name']));
            if ($multiPart && $selected['label'] === null) {
                $label = ($part['label'] ?? Str::headline($part['alias'])).' '.$label;
            }

            $field = array_merge($source, ['label' => $label]);

            if ($selected['meta']) {
                $metaFields[] = array_merge($field, ['meta_key' => $selected['key'], 'display' => true]);
            } else {
                $fields[$selected['key']] = array_merge($field, ['name' => $selected['key']]);
            }
        }

        return [$fields, $metaFields];
    }

    /** @param class-string<Model> $model */
    private function upsert(string $model, ?string $uid, string $slug, string $name, string $description, array $schema): Model
    {
        $record = ($uid ? $model::where('uid', $uid)->first() : null) ?? $model::where('slug', $slug)->first();

        if ($record) {
            $record->update([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'schema' => $schema,
                'status' => EntityStatus::ACTIVE,
                'updated_by' => auth()->id(),
            ]);

            return $record;
        }

        return $model::create([
            'uid' => (string) Str::ulid(),
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'schema' => $schema,
            'extra_info' => [],
            'status' => EntityStatus::ACTIVE,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }
}
