<?php

namespace Iquesters\Foundation\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Iquesters\Foundation\Constants\Constant as FormSchemaConstant;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Services\Schema\GeneratedSchemaStore;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationType;

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
        private readonly GeneratedSchemaStore $store,
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
        ], $fields, $metaFields, $this->relationFields($businessEntity, $definition));

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

    /** A form and its own list (default). */
    public const OUTPUT_FORM_TABLE = 'form_table';

    /** A form only, e.g. used in an existing table (meta use_in_table). */
    public const OUTPUT_FORM = 'form';

    /** No screens: the API only, for a module's own hand-made form. */
    public const OUTPUT_API = 'api';

    public const OUTPUTS = [self::OUTPUT_FORM_TABLE, self::OUTPUT_FORM, self::OUTPUT_API];

    /**
     * Create or update the stored schemas and remember their uids, as the
     * business entity's meta "output" asks:
     * - form_table: the form and its own list table;
     * - form: the form only;
     * - api: no schemas (the API works the same).
     * Schemas it no longer wants are deactivated. Meta use_in_table (a table
     * schema slug, e.g. "user-table") makes that existing table open this
     * form for Create and for its rows.
     *
     * @return array{form_schema_uid: ?string, table_schema_uid: ?string}
     */
    public function sync(BusinessEntity $businessEntity, ?BusinessEntityDefinition $definition = null): array
    {
        $definition ??= $businessEntity->parsedDefinition();
        $output = self::outputOf($businessEntity);
        $name = $businessEntity->name;
        $attributes = fn (string $slug, string $label, array $schema) => [
            'slug' => $slug,
            'name' => "{$name} {$label}",
            'description' => 'Auto-generated '.strtolower($label)." for the {$name} business entity",
            'schema' => $schema,
            'status' => EntityStatus::ACTIVE,
        ];
        $formFind = ['uid' => $businessEntity->getMeta('form_schema_uid'), 'slugs' => [self::formSlug($businessEntity)]];
        $tableFind = ['uid' => $businessEntity->getMeta('table_schema_uid'), 'slugs' => [self::tableSlug($businessEntity)]];
        $formUid = null;
        $tableUid = null;

        if ($output === self::OUTPUT_API) {
            $this->deactivate(GeneratedSchemaStore::FORM, $formFind);
        } else {
            $formUid = $this->store->save(GeneratedSchemaStore::FORM, $formFind,
                $attributes(self::formSlug($businessEntity), 'Form', $this->formSchema($businessEntity, $definition))
            )->uid;
        }

        if ($output === self::OUTPUT_FORM_TABLE) {
            $tableUid = $this->store->save(GeneratedSchemaStore::TABLE, $tableFind,
                $attributes(self::tableSlug($businessEntity), 'Table', $this->tableSchema($businessEntity, $definition, $formUid))
            )->uid;
        } else {
            $this->deactivate(GeneratedSchemaStore::TABLE, $tableFind);
        }

        $businessEntity->setMeta('form_schema_uid', $formUid);
        $businessEntity->setMeta('table_schema_uid', $tableUid);

        if ($formUid !== null && ($useIn = $businessEntity->getMeta('use_in_table'))) {
            $this->useFormInTable($businessEntity, (string) $useIn);
        }

        return ['form_schema_uid' => $formUid, 'table_schema_uid' => $tableUid];
    }

    public static function outputOf(BusinessEntity $businessEntity): string
    {
        $output = $businessEntity->getMeta('output');

        return in_array($output, self::OUTPUTS, true) ? $output : self::OUTPUT_FORM_TABLE;
    }

    /** A schema generated before that is no longer wanted. */
    private function deactivate(string $kind, array $find): void
    {
        $this->store->find($kind, $find)?->update(['status' => 'inactive', 'updated_by' => auth()->id()]);
    }

    /**
     * Make an existing table schema open this business entity's form: its
     * Create button ("create.form") and its rows ("form_schema_uid" and the
     * columns' "form-schema-uid"). The table keeps its own columns and data.
     *
     * The form the table opened before is remembered (meta
     * use_in_table_previous_form), so restoreTable() can put it back when the
     * business entity stops using the table or is deleted.
     *
     * @throws \InvalidArgumentException when the table schema doesn't exist
     */
    public function useFormInTable(BusinessEntity $businessEntity, string $tableSlug): void
    {
        $table = $this->store->find(GeneratedSchemaStore::TABLE, ['slugs' => [$tableSlug]]);

        if (! $table) {
            throw new \InvalidArgumentException("The table \"{$tableSlug}\" doesn't exist. Choose another table under \"Use the form in\".");
        }

        $formSlug = self::formSlug($businessEntity);
        $schema = self::schemaArray($table);
        $current = self::tableForm($schema);

        if ($current !== null && $current !== $formSlug) {
            $businessEntity->setMeta('use_in_table_previous_form', $current);
        }

        $table->update(['schema' => self::withTableForm($schema, $formSlug), 'updated_by' => auth()->id()]);
    }

    /** Give a table back the form it opened before this business entity used it. */
    public function restoreTable(BusinessEntity $businessEntity, string $tableSlug): void
    {
        $table = $this->store->find(GeneratedSchemaStore::TABLE, ['slugs' => [$tableSlug]]);
        $previous = $businessEntity->getMeta('use_in_table_previous_form');

        if ($table && $previous && self::tableForm(self::schemaArray($table)) === self::formSlug($businessEntity)) {
            $table->update(['schema' => self::withTableForm(self::schemaArray($table), $previous), 'updated_by' => auth()->id()]);
        }

        $businessEntity->setMeta('use_in_table_previous_form', null);
    }

    /** Turn off the generated form and list (the business entity is deleted). */
    public function deactivateAll(BusinessEntity $businessEntity): void
    {
        $this->deactivate(GeneratedSchemaStore::FORM, ['uid' => $businessEntity->getMeta('form_schema_uid'), 'slugs' => [self::formSlug($businessEntity)]]);
        $this->deactivate(GeneratedSchemaStore::TABLE, ['uid' => $businessEntity->getMeta('table_schema_uid'), 'slugs' => [self::tableSlug($businessEntity)]]);
    }

    private static function schemaArray(Model $table): array
    {
        return is_array($table->schema) ? $table->schema : (json_decode((string) $table->schema, true) ?: []);
    }

    /** The form a table schema opens (create button, else rows). */
    private static function tableForm(array $schema): ?string
    {
        return $schema['create']['form'] ?? $schema['form_schema_uid'] ?? null;
    }

    private static function withTableForm(array $schema, string $formSlug): array
    {
        $schema['form_schema_uid'] = $formSlug;
        $schema['create'] = ['form' => $formSlug] + (is_array($schema['create'] ?? null) ? $schema['create'] : []);
        $schema['create']['form'] = $formSlug;

        foreach ($schema['dt-options']['columns'] ?? [] as $i => $column) {
            if (is_array($column) && isset($column['form-schema-uid'])) {
                $schema['dt-options']['columns'][$i]['form-schema-uid'] = $formSlug;
            }
        }

        return $schema;
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

    /**
     * Form fields for the parts sent under their alias:
     * - a list of rows (has_many, morph_many): a repeatable group ("type":
     *   "repeater") whose columns are the part's selected fields;
     * - a reference: a select (one) or checkboxes (many) whose options come
     *   from the business entity options endpoint.
     */
    public function relationFields(BusinessEntity $businessEntity, BusinessEntityDefinition $definition): array
    {
        $entities = Entity::whereIn('uid', array_filter(array_column($definition->parts(), 'entity_uid')))->get()->keyBy('uid');
        $fields = [];

        foreach ($definition->leafAliases() as $alias) {
            $part = $definition->part($alias);
            $label = $part['label'] ?? Str::headline($alias);

            if ($definition->isRows($alias)) {
                $entity = $entities[$part['entity_uid']] ?? null;
                $main = $entity ? BusinessEntityDefinitionValidator::mainFields($entity) : [];
                $meta = $entity ? BusinessEntityDefinitionValidator::metaFields($entity) : [];
                // The entity's definition of each selected field, under the plain field name.
                $pick = fn (array $defs, array $selected, string $key, string $idKey) => array_map(
                    fn ($s) => array_merge($defs[$s[$key]] ?? [], [$idKey => $s[$key], 'display' => true], $s['label'] ? ['label' => $s['label']] : []),
                    $selected
                );

                $fields[] = [
                    'id' => $alias,
                    'type' => 'repeater',
                    'label' => $label,
                    'addLabel' => 'Add '.Str::lower(Str::singular($label)),
                    'fields' => array_merge(
                        $this->forms->formFields($pick($main, $part['fields'], 'field', 'name'), false),
                        $this->forms->formFields($pick($meta, $part['meta_fields'], 'meta_key', 'meta_key'), true),
                    ),
                ];

                continue;
            }

            $many = $definition->type($alias)->cardinality() === RelationType::MANY;
            $fields[] = [
                'id' => $alias,
                'type' => $many ? 'checkbox' : 'select',
                'label' => $label,
                'optionsFrom' => array_filter([
                    'url' => url(self::API_PREFIX.'/options/'.$businessEntity->slug.'/'.$alias),
                    'empty' => $many ? null : '— None —',
                ]),
            ];
        }

        return $fields;
    }
}
