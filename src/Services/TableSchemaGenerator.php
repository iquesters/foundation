<?php

namespace Iquesters\Foundation\Services;

use Throwable;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\System\Traits\Loggable;

class TableSchemaGenerator
{
    use Loggable;

    public function generate(Entity $entity, array $customFields, string $formSchemaUid): array
    {
        $this->logMethodStart('Generating table schema');

        try {
            $columns = [
                [
                    'data' => 'id',
                    'title' => 'ID',
                    'visible' => true,
                ],
            ];

            foreach (array_slice(array_values($customFields), 0, 2) as $field) {
                $columns[] = [
                    'data' => $field['name'],
                    'title' => $field['label'],
                    'visible' => true,
                    'link' => true,
                    'form-schema-uid' => $formSchemaUid,
                ];
            }

            $columns[] = [
                'data' => 'status',
                'title' => 'Status',
                'visible' => true,
            ];

            $baseTableName = $entity->getMeta('table_name') ?: $this->generateTableName($entity->entity_name);

            $schema = [
                'entity' => $this->pluralizeTableName($baseTableName),
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

            $this->logMethodEnd('Table schema generated successfully');

            return $schema;
        } catch (Throwable $throwable) {
            $this->logError('Failed to generate table schema: ' . $throwable->getMessage());
            throw $throwable;
        }
    }

    private function generateTableName(string $entityName): string
    {
        return Str::snake(Str::pluralStudly($entityName));
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
            in_array(substr($tableName, -1), ['s', 'x', 'z'], true) ||
            in_array(substr($tableName, -2), ['sh', 'ch'], true)
        ) {
            return $tableName . 'es';
        }

        return $tableName . 's';
    }
}
