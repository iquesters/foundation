<?php

namespace Iquesters\Foundation\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\System\Traits\Loggable;
use Iquesters\UserInterface\Models\FormSchema;

class FormSchemaService
{
    use Loggable;

    public function createAndAttach(
        Model $schemaOwner,
        string $slug,
        array $customFields,
        array $metaFields = [],
        array $options = []
    ): string
    {
        $this->logInfo('Creating and attaching form schema for ' . $this->resolveName($schemaOwner));

        $formSchemaUid = $this->create($schemaOwner, $slug, $customFields, $metaFields, $options);
        $this->saveMeta($schemaOwner, 'form_schema_uid', $formSchemaUid);

        $this->logInfo('Attached form schema UID ' . $formSchemaUid);

        return $formSchemaUid;
    }

    public function create(
        Model $schemaOwner,
        string $slug,
        array $customFields,
        array $metaFields = [],
        array $options = []
    ): string
    {
        $name = $this->resolveName($schemaOwner);

        $this->logInfo('Creating form schema record for ' . $name);

        $formSchemaRecord = FormSchema::create([
            'uid' => Str::ulid(),
            'slug' => $slug . '-form',
            'name' => $name . ' Form',
            'description' => 'Auto-generated form for ' . $name,
            'schema' => $this->generate($schemaOwner, $customFields, $metaFields, $options),
            'extra_info' => [],
            'status' => EntityStatus::ACTIVE,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->logInfo('Created form schema record UID ' . $formSchemaRecord->uid);

        return $formSchemaRecord->uid;
    }

    public function ensureGenerated(
        Model $schemaOwner,
        array $customFields,
        array $metaFields = [],
        array $options = []
    ): string
    {
        $options = $options ?: $this->resolveSchemaOptions($schemaOwner);
        $formSchemaUid = $this->getMetaValue($schemaOwner, 'form_schema_uid');
        $formSchemaSlug = $schemaOwner->slug . '-form';

        $this->logInfo('Ensuring generated form schema for ' . $this->resolveName($schemaOwner));

        if (! $formSchemaUid) {
            $newFormSchemaUid = $this->create($schemaOwner, $schemaOwner->slug, $customFields, $metaFields, $options);
            $this->saveMeta($schemaOwner, 'form_schema_uid', $newFormSchemaUid, false);

            Log::info('Generated form schema recreated because form_schema_uid was missing', [
                'schema_owner_uid' => $schemaOwner->uid,
                'form_schema_uid' => $newFormSchemaUid,
            ]);

            return $newFormSchemaUid;
        }

        $formSchemaRecord = FormSchema::where('uid', $formSchemaUid)->first();

        if (! $formSchemaRecord) {
            $formSchemaRecord = FormSchema::where('slug', $formSchemaSlug)->first();
        }

        if (! $formSchemaRecord) {
            $newFormSchemaUid = $this->create($schemaOwner, $schemaOwner->slug, $customFields, $metaFields, $options);
            $this->saveMeta($schemaOwner, 'form_schema_uid', $newFormSchemaUid, false);

            Log::info('Generated form schema recreated because stored schema record was missing', [
                'schema_owner_uid' => $schemaOwner->uid,
                'missing_form_schema_uid' => $formSchemaUid,
                'new_form_schema_uid' => $newFormSchemaUid,
            ]);

            return $newFormSchemaUid;
        }

        $name = $this->resolveName($schemaOwner);

        $formSchemaRecord->update([
            'name' => $name . ' Form',
            'description' => 'Auto-generated form for ' . $name,
            'schema' => $this->generate($schemaOwner, $customFields, $metaFields, $options),
            'updated_by' => auth()->id(),
        ]);

        $this->logInfo('Updated generated form schema UID ' . $formSchemaRecord->uid);

        if ($formSchemaRecord->uid !== $formSchemaUid) {
            $this->saveMeta($schemaOwner, 'form_schema_uid', $formSchemaRecord->uid, false);
        }

        return $formSchemaRecord->uid;
    }

    public function generate(Model $schemaOwner, array $customFields, array $metaFields = [], array $options = []): array
    {
        $name = $this->resolveName($schemaOwner);
        $tableName = $this->generateTableName($name);
        $isBusinessEntity = ! empty($options['is_business_entity']);

        $fields = array_merge(
            $this->buildFields($customFields, false),
            $this->buildFields($this->filterDisplayableMetaFields($metaFields), true)
        );

        if (! empty($options['is_business_entity'])) {
            $businessEntityKey = (string) (
                $schemaOwner->slug
                ?? $schemaOwner->business_entity_name
                ?? $schemaOwner->uid
                ?? $tableName
            );

            return [
                'business_entity' => $businessEntityKey,
                'floatinglabel' => false,
                'enctype' => 'multipart/form-data',
                'allowView' => true,
                'allowEdit' => true,
                'allowDelete' => true,
                'allowSubmit' => true,
                'allowCancel' => true,
                'modes' => [
                    'create' => $this->buildBusinessEntityMode('POST', 'store', $businessEntityKey, $name, 'Create', 'fas fa-database', 'creating a new'),
                    'view' => $this->buildBusinessEntityMode('GET', 'show', $businessEntityKey, $name, 'View', 'fas fa-eye', 'viewing this', true),
                    'edit' => $this->buildBusinessEntityMode('PUT', 'update', $businessEntityKey, $name, 'Edit', 'fas fa-pen', 'editing this', true),
                    'delete' => $this->buildBusinessEntityMode('DELETE', 'delete', $businessEntityKey, $name, 'Delete', 'fas fa-trash', 'deleting this', true),
                ],
                'fields' => $fields,
                'actions' => [
                    [
                        'icon' => 'far fa-save',
                        'type' => 'submit',
                        'route' => '#',
                        'element' => [
                            'type' => 'button',
                            'color' => 'success',
                        ],
                    ],
                    [
                        'icon' => 'far fa-times-circle',
                        'text' => 'Cancel',
                        'type' => 'cancel',
                        'route' => '#',
                        'element' => [
                            'type' => 'button',
                            'color' => 'secondary',
                        ],
                    ],
                ],
            ];
        }

        return [
            'info' => [
                'icon' => 'far fa-lightbulb',
                'innerHTML' => 'You are creating a new ' . $name,
            ],
            'entity' => $tableName,
            'method' => 'POST',
            'endpoint' => url('/api/entity/store/' . $tableName),
            'floatinglabel' => false,
            'enctype' => 'multipart/form-data',
            'header' => [
                'icon' => 'fas fa-database',
                'text' => 'Create ' . $name,
            ],
            'fields' => $fields,
            'actions' => [
                [
                    'icon' => 'far fa-save',
                    'type' => 'submit',
                    'route' => '#',
                    'element' => [
                        'type' => 'button',
                        'color' => 'success',
                    ],
                ],
            ],
        ];
    }

    public function resolveSchemaOptions(Model $schemaOwner): array
    {
        return [
            'is_business_entity' => property_exists($schemaOwner, 'business_entity_name')
                || isset($schemaOwner->business_entity_name),
        ];
    }

    private function buildBusinessEntityMode(
        string $method,
        string $action,
        string $businessEntityKey,
        string $name,
        string $titleVerb,
        string $icon,
        string $descriptionPrefix,
        bool $withUid = false
    ): array {
        $endpoint = url('/api/business-entity/' . $action . '/' . $businessEntityKey);

        if ($withUid) {
            $endpoint .= '/{uid}';
        }

        return [
            'method' => $method,
            'endpoint' => $endpoint,
            'header' => [
                'icon' => $icon,
                'title' => $titleVerb . ' ' . $name,
                'description' => 'You are ' . $descriptionPrefix . ' ' . strtolower($name) . '.',
            ],
        ];
    }

    private function buildFields(array $fields, bool $isMetaField): array
    {
        $schemaFields = [];
        $inputTypeMapping = [
            'text' => 'text',
            'textarea' => 'textarea',
            'number' => 'number',
            'email' => 'email',
            'datetime-local' => 'datetime-local',
            'date' => 'date',
            'time' => 'time',
            'checkbox' => 'checkbox',
            'select' => 'select',
        ];

        foreach ($fields as $field) {
            $fieldId = $isMetaField ? ($field['meta_key'] ?? null) : ($field['name'] ?? null);
            $fieldLabel = $field['label'] ?? Str::headline((string) $fieldId);

            if (! $fieldId) {
                continue;
            }

            $formField = [
                'id' => $fieldId,
                'type' => $inputTypeMapping[$field['input_type']] ?? 'text',
                'label' => $fieldLabel,
                'placeholder' => 'Enter ' . strtolower($fieldLabel),
                'helpertext' => $fieldLabel . ' field',
                'required' => ! empty($field['required']),
                'size' => [
                    'md' => 12,
                ],
            ];

            if (! empty($field['maxlength'])) {
                $formField['maxLength'] = $field['maxlength'];
            }

            if (! empty($field['required'])) {
                $formField['messages'] = [
                    'required' => $fieldLabel . ' is required',
                ];
            }

            if ($isMetaField) {
                $formField['meta'] = true;
            }

            $schemaFields[] = $formField;
        }

        return $schemaFields;
    }

    private function filterDisplayableMetaFields(array $metaFields): array
    {
        return array_values(array_filter($metaFields, function ($field) {
            return ($field['display'] ?? true) === true || ($field['display'] ?? 1) === 1;
        }));
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

    private function getMetaValue(Model $schemaOwner, string $metaKey): ?string
    {
        if (method_exists($schemaOwner, 'getMeta')) {
            return $schemaOwner->getMeta($metaKey);
        }

        return optional(
            $schemaOwner->metas()->where('meta_key', $metaKey)->first()
        )->meta_value;
    }

    private function saveMeta(Model $schemaOwner, string $metaKey, mixed $metaValue, bool $isCreate = true): void
    {
        $metaData = [
            'meta_value' => $metaValue,
            'status' => 'active',
            'updated_by' => auth()->id(),
        ];

        if ($isCreate) {
            $metaData['created_by'] = auth()->id();
        }

        $schemaOwner->metas()->updateOrCreate(
            ['meta_key' => $metaKey],
            $metaData
        );
    }
}
