<?php

namespace Iquesters\Foundation\Services;

use Illuminate\Support\Str;
use Iquesters\Foundation\Constants\Constant as FormSchemaConstant;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\System\Traits\Loggable;
use Throwable;

class FormSchemaGenerator
{
    use Loggable;

    public function generate(Entity $entity, array $customFields, array $metaFields = []): array
    {
        $entityTable = $this->generateTableName($entity->entity_name);

        return $this->build(Str::headline($entity->entity_name), $entityTable, [
            FormSchemaConstant::MODE_CREATE => url('/api/foundation/v1/entity/store/'.$entityTable),
            FormSchemaConstant::MODE_VIEW => url('/api/foundation/v1/entity/show/'.$entityTable.'/{uid}'),
            FormSchemaConstant::MODE_EDIT => url('/api/foundation/v1/entity/update/'.$entityTable.'/{uid}'),
            FormSchemaConstant::MODE_DELETE => url('/api/foundation/v1/entity/delete/'.$entityTable.'/{uid}'),
        ], $customFields, $metaFields);
    }

    /**
     * A form schema for any resource: generate() uses it for an entity table,
     * BusinessEntitySchemaGenerator for a business entity's combined record.
     *
     * @param  string  $entityName  display name, e.g. "Customer"
     * @param  string  $entityKey  the schema's "entity" value
     * @param  array<string, string>  $endpoints  mode (create/view/edit/delete) => URL, with a {uid} placeholder
     * @param  array  $extraFields  ready-made form fields appended after the generated ones
     */
    public function build(string $entityName, string $entityKey, array $endpoints, array $customFields, array $metaFields = [], array $extraFields = []): array
    {
        $this->logMethodStart('Generating form schema');

        try {
            $fields = array_merge(
                $this->buildFormSchemaFields($customFields, false),
                $this->buildFormSchemaFields($this->filterDisplayableMetaFields($metaFields), true),
                $extraFields,
            );

            $modes = [
                FormSchemaConstant::MODE_CREATE => [FormSchemaConstant::METHOD_POST, FormSchemaConstant::HEADER_ICON_CREATE, 'Create', 'creating a new'],
                FormSchemaConstant::MODE_VIEW => [FormSchemaConstant::METHOD_GET, FormSchemaConstant::HEADER_ICON_VIEW, 'View', 'viewing this'],
                FormSchemaConstant::MODE_EDIT => [FormSchemaConstant::METHOD_PUT, FormSchemaConstant::HEADER_ICON_EDIT, 'Edit', 'editing this'],
                FormSchemaConstant::MODE_DELETE => [FormSchemaConstant::METHOD_DELETE, FormSchemaConstant::HEADER_ICON_DELETE, 'Delete', 'deleting this'],
            ];

            $schemaModes = [];
            foreach ($modes as $mode => [$method, $icon, $verb, $phrase]) {
                $schemaModes[$mode] = [
                    'method' => $method,
                    'endpoint' => $endpoints[$mode] ?? '',
                    'header' => [
                        'icon' => $icon,
                        'title' => $verb.' '.$entityName,
                        'description' => 'You are '.$phrase.' '.Str::lower($entityName).'.',
                    ],
                ];
            }

            $schema = [
                'entity' => $entityKey,
                'floatinglabel' => false,
                'enctype' => FormSchemaConstant::ENCTYPE,
                'allowView' => true,
                'allowEdit' => true,
                'allowDelete' => true,
                'allowSubmit' => true,
                'allowCancel' => true,
                'modes' => $schemaModes,
                'fields' => $fields,
                'actions' => [
                    [
                        'icon' => FormSchemaConstant::ACTION_ICON_SUBMIT,
                        'type' => FormSchemaConstant::ACTION_TYPE_SUBMIT,
                        'route' => FormSchemaConstant::ACTION_ROUTE_DEFAULT,
                        'element' => [
                            'type' => FormSchemaConstant::ELEMENT_TYPE_BUTTON,
                            'color' => FormSchemaConstant::ELEMENT_COLOR_SUCCESS,
                        ],
                    ],
                    [
                        'icon' => FormSchemaConstant::ACTION_ICON_CANCEL,
                        'text' => 'Cancel',
                        'type' => FormSchemaConstant::ACTION_TYPE_CANCEL,
                        'route' => FormSchemaConstant::ACTION_ROUTE_DEFAULT,
                        'element' => [
                            'type' => FormSchemaConstant::ELEMENT_TYPE_BUTTON,
                            'color' => FormSchemaConstant::ELEMENT_COLOR_SECONDARY,
                        ],
                    ],
                ],
            ];

            $this->logMethodEnd('Form schema generated successfully');

            return $schema;
        } catch (Throwable $throwable) {
            $this->logError('Failed to generate form schema: '.$throwable->getMessage());
            throw $throwable;
        }
    }

    /**
     * Form fields for entity field definitions (main fields by "name", meta
     * fields by "meta_key"), e.g. the columns of a repeatable group.
     */
    public function formFields(array $fields, bool $isMetaField): array
    {
        return $this->buildFormSchemaFields($isMetaField ? $this->filterDisplayableMetaFields($fields) : $fields, $isMetaField);
    }

    private function buildFormSchemaFields(array $fields, bool $isMetaField): array
    {
        $this->logMethodStart('Building form schema fields');

        try {
            $schemaFields = [];
            $inputTypeMapping = [
                'text' => 'text',
                'hidden' => 'hidden',
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
                    'type' => $this->resolveFormFieldType($field, $inputTypeMapping),
                    'label' => $fieldLabel,
                    'placeholder' => 'Enter '.strtolower($fieldLabel),
                    'helpertext' => $fieldLabel.' field',
                    'required' => ! empty($field['required']),
                ];

                $fieldSize = $this->buildFieldSizeConfig($field);
                if ($fieldSize !== null) {
                    $formField[$fieldSize['key']] = $fieldSize['value'];
                }

                if (! empty($field['maxlength'])) {
                    $formField['maxLength'] = $field['maxlength'];
                }

                if (! empty($field['required'])) {
                    $formField['messages'] = [
                        'required' => $fieldLabel.' is required',
                    ];
                }

                if ($isMetaField) {
                    $formField['meta'] = true;
                }

                $schemaFields[] = $formField;
            }

            $this->logMethodEnd('Form schema fields built successfully');

            return $schemaFields;
        } catch (Throwable $throwable) {
            $this->logError('Failed to build form schema fields: '.$throwable->getMessage());
            throw $throwable;
        }
    }

    private function buildFieldSizeConfig(array $field): ?array
    {
        $this->logMethodStart('Building field size config');

        try {
            if (array_key_exists('size', $field) && $field['size'] !== null && $field['size'] !== '') {
                $normalizedSize = $this->normalizeFieldSizeValue($field['size']);

                if ($normalizedSize !== null) {
                    $this->logMethodEnd('Field size config built successfully');

                    return [
                        'key' => 'size',
                        'value' => $normalizedSize,
                    ];
                }
            }

            if (array_key_exists('gridSize', $field) && $field['gridSize'] !== null && $field['gridSize'] !== '') {
                $normalizedGridSize = $this->normalizeFieldSizeValue($field['gridSize']);

                if ($normalizedGridSize !== null) {
                    $this->logMethodEnd('Field size config built successfully');

                    return [
                        'key' => 'gridSize',
                        'value' => $normalizedGridSize,
                    ];
                }
            }

            $this->logMethodEnd('Using default field size config');

            return [
                'key' => 'size',
                'value' => FormSchemaConstant::DEFAULT_SIZE,
            ];
        } catch (Throwable $throwable) {
            $this->logError('Failed to build field size config: '.$throwable->getMessage());
            throw $throwable;
        }
    }

    private function resolveFormFieldType(array $field, array $inputTypeMapping): string
    {
        $this->logMethodStart('Resolving form field type');

        try {
            $inputType = $field['input_type'] ?? null;

            if (is_string($inputType) && isset($inputTypeMapping[$inputType])) {
                $this->logMethodEnd('Resolved form field type from input type');

                return $inputTypeMapping[$inputType];
            }

            $fieldType = strtolower((string) ($field['type'] ?? ''));

            $resolvedType = match ($fieldType) {
                'text', 'longtext' => 'textarea',
                'integer', 'bigint', 'decimal', 'float', 'double' => 'number',
                'boolean' => 'checkbox',
                'date' => 'date',
                'datetime', 'timestamp' => 'datetime-local',
                'time' => 'time',
                'email' => 'email',
                default => 'text',
            };

            $this->logMethodEnd('Resolved form field type from field type fallback');

            return $resolvedType;
        } catch (Throwable $throwable) {
            $this->logError('Failed to resolve form field type: '.$throwable->getMessage());
            throw $throwable;
        }
    }

    private function normalizeFieldSizeValue(mixed $configuredSize): mixed
    {
        $this->logMethodStart('Normalizing field size value');

        try {
            if (is_numeric($configuredSize)) {
                $normalizedSize = array_fill_keys(FormSchemaConstant::BREAKPOINTS, (int) $configuredSize);
                $this->logMethodEnd('Field size value normalized successfully');

                return $normalizedSize;
            }

            if (! is_array($configuredSize)) {
                $this->logMethodEnd('Field size value is not configurable');

                return null;
            }

            $sizes = [];
            $fallbackSize = null;

            if (array_key_exists('md', $configuredSize) && is_numeric($configuredSize['md'])) {
                $fallbackSize = (int) $configuredSize['md'];
            }

            foreach (FormSchemaConstant::BREAKPOINTS as $breakpoint) {
                if (array_key_exists($breakpoint, $configuredSize) && is_numeric($configuredSize[$breakpoint])) {
                    $sizes[$breakpoint] = (int) $configuredSize[$breakpoint];

                    continue;
                }

                if ($fallbackSize !== null) {
                    $sizes[$breakpoint] = $fallbackSize;
                }
            }

            $normalizedSize = empty($sizes) ? null : $sizes;
            $this->logMethodEnd('Field size value normalized successfully');

            return $normalizedSize;
        } catch (Throwable $throwable) {
            $this->logError('Failed to normalize field size value: '.$throwable->getMessage());
            throw $throwable;
        }
    }

    private function filterDisplayableMetaFields(array $metaFields): array
    {
        $this->logMethodStart('Filtering displayable meta fields');

        try {
            $filteredMetaFields = array_values(array_filter($metaFields, function ($field) {
                return ($field['display'] ?? true) === true || ($field['display'] ?? 1) === 1;
            }));

            $this->logMethodEnd('Displayable meta fields filtered successfully');

            return $filteredMetaFields;
        } catch (Throwable $throwable) {
            $this->logError('Failed to filter displayable meta fields: '.$throwable->getMessage());
            throw $throwable;
        }
    }

    private function generateTableName(string $entityName): string
    {
        $this->logMethodStart('Generating table name');

        try {
            $tableName = Str::snake(Str::pluralStudly($entityName));

            $this->logMethodEnd('Table name generated successfully');

            return $tableName;
        } catch (Throwable $throwable) {
            $this->logError('Failed to generate table name: '.$throwable->getMessage());
            throw $throwable;
        }
    }
}
