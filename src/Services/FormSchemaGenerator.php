<?php

namespace Iquesters\Foundation\Services;

use Throwable;
use Illuminate\Support\Str;
use Iquesters\Foundation\Constants\Constant as FormSchemaConstant;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\System\Traits\Loggable;

class FormSchemaGenerator
{
    use Loggable;

    public function generate(Entity $entity, array $customFields, array $metaFields = []): array
    {
        $this->logMethodStart('Generating form schema');

        try {
            $entityName = Str::headline($entity->entity_name);
            $entityTable = $this->generateTableName($entity->entity_name);
            $fields = array_merge(
                $this->buildFormSchemaFields($customFields, false),
                $this->buildFormSchemaFields($this->filterDisplayableMetaFields($metaFields), true)
            );

            $schema = [
                'entity' => $entityTable,
                'floatinglabel' => false,
                'enctype' => FormSchemaConstant::ENCTYPE,
                'allowView' => true,
                'allowEdit' => true,
                'allowDelete' => true,
                'allowSubmit' => true,
                'allowCancel' => true,
                'modes' => [
                    FormSchemaConstant::MODE_CREATE => [
                        'method' => FormSchemaConstant::METHOD_POST,
                        'endpoint' => url('/api/entity/store/' . $entityTable),
                        'header' => [
                            'icon' => FormSchemaConstant::HEADER_ICON_CREATE,
                            'title' => 'Create ' . $entityName,
                            'description' => 'You are creating a new ' . Str::lower($entityName) . '.',
                        ],
                    ],
                    FormSchemaConstant::MODE_VIEW => [
                        'method' => FormSchemaConstant::METHOD_GET,
                        'endpoint' => url('/api/entity/show/' . $entityTable . '/{uid}'),
                        'header' => [
                            'icon' => FormSchemaConstant::HEADER_ICON_VIEW,
                            'title' => 'View ' . $entityName,
                            'description' => 'You are viewing this ' . Str::lower($entityName) . '.',
                        ],
                    ],
                    FormSchemaConstant::MODE_EDIT => [
                        'method' => FormSchemaConstant::METHOD_PUT,
                        'endpoint' => url('/api/entity/update/' . $entityTable . '/{uid}'),
                        'header' => [
                            'icon' => FormSchemaConstant::HEADER_ICON_EDIT,
                            'title' => 'Edit ' . $entityName,
                            'description' => 'You are editing this ' . Str::lower($entityName) . '.',
                        ],
                    ],
                    FormSchemaConstant::MODE_DELETE => [
                        'method' => FormSchemaConstant::METHOD_DELETE,
                        'endpoint' => url('/api/entity/delete/' . $entityTable . '/{uid}'),
                        'header' => [
                            'icon' => FormSchemaConstant::HEADER_ICON_DELETE,
                            'title' => 'Delete ' . $entityName,
                            'description' => 'You are deleting this ' . Str::lower($entityName) . '.',
                        ],
                    ],
                ],
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
            $this->logError('Failed to generate form schema: ' . $throwable->getMessage());
            throw $throwable;
        }
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
                    'type' => $inputTypeMapping[$field['input_type']] ?? 'text',
                    'label' => $fieldLabel,
                    'placeholder' => 'Enter ' . strtolower($fieldLabel),
                    'helpertext' => $fieldLabel . ' field',
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
                        'required' => $fieldLabel . ' is required',
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
            $this->logError('Failed to build form schema fields: ' . $throwable->getMessage());
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
            $this->logError('Failed to build field size config: ' . $throwable->getMessage());
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
            $this->logError('Failed to normalize field size value: ' . $throwable->getMessage());
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
            $this->logError('Failed to filter displayable meta fields: ' . $throwable->getMessage());
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
            $this->logError('Failed to generate table name: ' . $throwable->getMessage());
            throw $throwable;
        }
    }
}
