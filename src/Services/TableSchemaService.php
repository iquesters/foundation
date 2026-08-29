<?php

namespace Iquesters\Foundation\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Iquesters\Foundation\System\Traits\Loggable;
use Iquesters\UserInterface\Models\TableSchema;

class TableSchemaService
{
    use Loggable;

    public function createAndAttach(
        Model $schemaOwner,
        string $slug,
        array $customFields,
        string $formSchemaUid,
        array $metaFields = [],
        array $options = []
    ): string
    {
        $this->logInfo('Creating and attaching table schema for ' . $this->resolveName($schemaOwner));

        $tableSchemaUid = $this->create($schemaOwner, $slug, $customFields, $formSchemaUid, $metaFields, $options);
        $this->saveMeta($schemaOwner, 'table_schema_uid', $tableSchemaUid);

        $this->logInfo('Attached table schema UID ' . $tableSchemaUid);

        return $tableSchemaUid;
    }

    public function ensureGenerated(
        Model $schemaOwner,
        string $slug,
        array $customFields,
        string $formSchemaUid,
        array $metaFields = [],
        array $options = []
    ): string
    {
        $tableSchemaUid = $this->getMetaValue($schemaOwner, 'table_schema_uid');
        $tableSchemaSlug = $slug . '-table';
        $schema = $this->generate($schemaOwner, $customFields, $formSchemaUid, $metaFields, $options);

        if (! $tableSchemaUid) {
            $tableSchemaUid = $this->create($schemaOwner, $slug, $customFields, $formSchemaUid, $metaFields, $options);
            $this->saveMeta($schemaOwner, 'table_schema_uid', $tableSchemaUid);

            return $tableSchemaUid;
        }

        $tableSchemaRecord = TableSchema::where('uid', $tableSchemaUid)->first()
            ?? TableSchema::where('slug', $tableSchemaSlug)->first();

        if (! $tableSchemaRecord) {
            $tableSchemaUid = $this->create($schemaOwner, $slug, $customFields, $formSchemaUid, $metaFields, $options);
            $this->saveMeta($schemaOwner, 'table_schema_uid', $tableSchemaUid);

            return $tableSchemaUid;
        }

        $name = $this->resolveName($schemaOwner);

        $tableSchemaRecord->update([
            'slug' => $tableSchemaSlug,
            'name' => $name . ' Table',
            'description' => 'Auto-generated table for ' . $name,
            'schema' => $schema,
            'updated_by' => auth()->id(),
        ]);

        if ($tableSchemaRecord->uid !== $tableSchemaUid) {
            $this->saveMeta($schemaOwner, 'table_schema_uid', $tableSchemaRecord->uid);
            $tableSchemaUid = $tableSchemaRecord->uid;
        }

        return $tableSchemaUid;
    }

    public function create(
        Model $schemaOwner,
        string $slug,
        array $customFields,
        string $formSchemaUid,
        array $metaFields = [],
        array $options = []
    ): string
    {
        $name = $this->resolveName($schemaOwner);

        $this->logInfo('Creating table schema record for ' . $name);

        $tableSchemaRecord = TableSchema::create([
            'uid' => Str::ulid(),
            'slug' => $slug . '-table',
            'name' => $name . ' Table',
            'description' => 'Auto-generated table for ' . $name,
            'schema' => $this->generate($schemaOwner, $customFields, $formSchemaUid, $metaFields, $options),
            'extra_info' => [],
            'status' => 'active',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->logInfo('Created table schema record UID ' . $tableSchemaRecord->uid);

        return $tableSchemaRecord->uid;
    }

    public function generate(
        Model $schemaOwner,
        array $customFields,
        string $formSchemaUid,
        array $metaFields = [],
        array $options = []
    ): array
    {
        if (! empty($options['is_business_entity'])) {
            return $this->generateBusinessEntitySchema($schemaOwner, $customFields, $metaFields, $formSchemaUid);
        }

        $columns = [
            [
                'data' => 'id',
                'title' => 'ID',
                'visible' => true,
            ],
        ];

        $fieldsToShow = array_slice(array_values($customFields), 0, 2);

        foreach ($fieldsToShow as $field) {
            $columns[] = [
                'data' => $field['name'],
                'title' => $field['label'],
                'visible' => true,
                'link' => true,
            ];
        }

        $columns[] = [
            'data' => 'status',
            'title' => 'Status',
            'visible' => true,
        ];

        return [
            'entity' => $this->pluralizeTableName($this->resolveBaseTableName($schemaOwner)),
            'form-schema-uid' => $formSchemaUid,
            'dt-options' => [
                'columns' => $columns,
                'options' => [
                    'pageLength' => 10,
                    'order' => [[0, 'desc']],
                    'responsive' => true,
                ],
            ],
            'default_view_mode' => 'inbox',
        ];
    }

    private function generateBusinessEntitySchema(
        Model $schemaOwner,
        array $customFields,
        array $metaFields,
        string $formSchemaUid
    ): array {
        $columns = [];

        $groupedFields = [];

        foreach ($customFields as $field) {
            $sourceEntity = $field['source_entity'] ?? 'unknown';
            $groupedFields[$sourceEntity]['primary'][] = $field;
        }

        foreach ($this->filterDisplayableMetaFields($metaFields) as $field) {
            $sourceEntity = $field['source_entity'] ?? 'unknown';
            $groupedFields[$sourceEntity]['meta'][] = $field;
        }

        foreach ($this->resolveBusinessEntityOrder($customFields, $metaFields) as $sourceEntity) {
            foreach ($groupedFields[$sourceEntity]['primary'] ?? [] as $field) {
                $columns[] = [
                    'data' => $this->formatBusinessEntityPrimaryData($field),
                    'title' => $this->formatBusinessEntityKeyTitle($field['source_field'] ?? $field['name']),
                    'visible' => true,
                    'link' => true,
                ];
            }

            foreach ($groupedFields[$sourceEntity]['meta'] ?? [] as $field) {
                $columns[] = [
                    'data' => $this->formatBusinessEntityMetaData($field),
                    'title' => $this->formatBusinessEntityKeyTitle($field['source_meta_key'] ?? $field['meta_key']),
                    'visible' => true,
                    'link' => true,
                ];
            }
        }

        return [
            'business_entity' => $this->resolveBusinessEntityIdentifier($schemaOwner),
            'form-schema-uid' => $formSchemaUid,
            'dt-options' => [
                'columns' => $columns,
                'options' => [
                    'pageLength' => 10,
                    'order' => [[0, 'desc']],
                    'responsive' => true,
                ],
            ],
            'default_view_mode' => 'inbox',
        ];
    }

    private function formatBusinessEntityPrimaryData(array $field): string
    {
        return $this->formatSourceTableName($field['source_entity'] ?? null)
            . '.'
            . ($field['source_field'] ?? $field['name']);
    }

    private function formatBusinessEntityMetaData(array $field): string
    {
        return $this->formatSourceTableName($field['source_entity'] ?? null)
            . '.meta.'
            . ($field['source_meta_key'] ?? $field['meta_key']);
    }

    private function formatBusinessEntityKeyTitle(?string $fieldKey): string
    {
        return $this->stripMetaPrefix((string) $fieldKey);
    }

    private function stripMetaPrefix(string $fieldKey): string
    {
        return preg_replace('/^m_/', '', $fieldKey) ?: $fieldKey;
    }

    private function formatSourceTableName(?string $tableName): string
    {
        if (! $tableName) {
            return 'Unknown';
        }

        return Str::lower(Str::singular($tableName));
    }

    private function resolveBusinessEntityOrder(array $customFields, array $metaFields): array
    {
        $order = [];

        foreach ($customFields as $field) {
            $sourceEntity = $field['source_entity'] ?? 'unknown';

            if (! in_array($sourceEntity, $order, true)) {
                $order[] = $sourceEntity;
            }
        }

        foreach ($this->filterDisplayableMetaFields($metaFields) as $field) {
            $sourceEntity = $field['source_entity'] ?? 'unknown';

            if (! in_array($sourceEntity, $order, true)) {
                $order[] = $sourceEntity;
            }
        }

        return $order;
    }

    private function filterDisplayableMetaFields(array $metaFields): array
    {
        return array_values(array_filter($metaFields, function ($field) {
            return ($field['display'] ?? true) === true || ($field['display'] ?? 1) === 1;
        }));
    }

    private function resolveBaseTableName(Model $schemaOwner): string
    {
        $tableMeta = $schemaOwner->metas()
            ->where('meta_key', 'table_name')
            ->first();

        return $tableMeta?->meta_value ?? $this->generateTableName($this->resolveName($schemaOwner));
    }

    private function resolveName(Model $schemaOwner): string
    {
        return (string) (
            $schemaOwner->entity_name
            ?? $schemaOwner->business_entity_name
            ?? $schemaOwner->name
            ?? $schemaOwner->slug
        );
    }

    private function generateTableName(string $name): string
    {
        $tableName = strtolower($name);
        $tableName = preg_replace('/[^a-z0-9]+/', '_', $tableName);

        return trim($tableName, '_');
    }

    private function pluralizeTableName(string $tableName): string
    {
        $pluralEndings = ['s', 'es', 'ies'];

        foreach ($pluralEndings as $ending) {
            if (substr($tableName, -strlen($ending)) === $ending) {
                return $tableName;
            }
        }

        if (substr($tableName, -1) === 'y') {
            return substr($tableName, 0, -1) . 'ies';
        }

        if (
            in_array(substr($tableName, -1), ['s', 'x', 'z'], true)
            || in_array(substr($tableName, -2), ['sh', 'ch'], true)
        ) {
            return $tableName . 'es';
        }

        return $tableName . 's';
    }

    private function saveMeta(Model $schemaOwner, string $metaKey, mixed $metaValue): void
    {
        $schemaOwner->metas()->updateOrCreate(
            ['meta_key' => $metaKey],
            [
                'meta_value' => $metaValue,
                'status' => 'active',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
    }

    private function getMetaValue(Model $schemaOwner, string $metaKey): ?string
    {
        if (method_exists($schemaOwner, 'getMeta')) {
            return $schemaOwner->getMeta($metaKey);
        }

        return optional(
            $schemaOwner->metas()->where('meta_key', $metaKey)->first()
        )?->meta_value;
    }

    private function resolveBusinessEntityIdentifier(Model $schemaOwner): string
    {
        return (string) (
            $schemaOwner->uid
            ?? $schemaOwner->slug
            ?? $schemaOwner->business_entity_name
            ?? $this->resolveName($schemaOwner)
        );
    }
}
