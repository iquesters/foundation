<?php

namespace Iquesters\Foundation\Services\Entity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Converts raw API payload values into what the column type expects, and
 * rejects values that can't be converted (InvalidArgumentException -> 400).
 */
class EntityValueNormalizer
{
    /** Main-table payload restricted to writable, defined columns, normalized. */
    public function mainData(array $payload, array $mainDefinitions, array $writableColumns): array
    {
        $data = [];

        foreach ($payload as $key => $value) {
            if (! in_array($key, $writableColumns, true) || ! array_key_exists($key, $mainDefinitions)) {
                continue;
            }

            $definition = $mainDefinitions[$key];
            $this->assertNotEmptyIfRequired($key, $value, $definition);
            $data[$key] = $this->fromDefinition($key, $value, $definition);
        }

        return $data;
    }

    /** Explicit meta payload (assoc or [{meta_key, meta_value}]) restricted to defined keys, normalized. */
    public function metaData(array $metaDefinitions, mixed $explicitMeta): array
    {
        $data = [];

        foreach ($this->flattenMeta($explicitMeta) as $key => $value) {
            if (! array_key_exists($key, $metaDefinitions)) {
                continue;
            }

            $definition = $metaDefinitions[$key];
            $this->assertNotEmptyIfRequired($key, $value, $definition);
            $data[$key] = $this->fromDefinition($key, $value, $definition);
        }

        return $data;
    }

    /** Accepts ['key' => value] or [['meta_key' => k, 'meta_value' => v], ...]. */
    public function flattenMeta(mixed $explicitMeta): array
    {
        if (! is_array($explicitMeta)) {
            return [];
        }

        $flat = [];

        foreach ($explicitMeta as $key => $value) {
            if (is_int($key) && is_array($value) && isset($value['meta_key'])) {
                $key = $value['meta_key'];
                $value = $value['meta_value'] ?? null;
            }

            $flat[$key] = $value;
        }

        return $flat;
    }

    public function assertNotEmptyIfRequired(string $column, mixed $value, array $definition): void
    {
        $nullable = (bool) ($definition['nullable'] ?? true);
        $required = (bool) ($definition['required'] ?? false);

        if (($value === null || $value === '') && ($required || ! $nullable)) {
            throw new \InvalidArgumentException("Field {$column} cannot be empty.");
        }
    }

    public function fromDefinition(string $column, mixed $value, array $definition): mixed
    {
        return $this->forColumnType($column, $value, $this->columnType($definition['type'] ?? 'string'));
    }

    public function columnType(string $fieldType): string
    {
        return match (strtolower($fieldType)) {
            'string', 'varchar' => 'varchar',
            'text' => 'text',
            'longtext' => 'longtext',
            'integer', 'bigint', 'smallint', 'tinyint' => 'integer',
            'decimal', 'float', 'double', 'real' => 'decimal',
            'boolean', 'bool' => 'boolean',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'time' => 'time',
            'char', 'uuid', 'ulid' => 'char',
            'json' => 'json',
            default => 'text',
        };
    }

    public function forColumnType(string $column, mixed $value, string $columnType): mixed
    {
        if ($value === '' && ! in_array($columnType, ['string', 'text', 'mediumtext', 'longtext'], true)) {
            return null;
        }

        if ($value === null) {
            return null;
        }

        if ($column === 'password' && is_string($value) && $value !== '') {
            return Hash::needsRehash($value) ? Hash::make($value) : $value;
        }

        return match ($columnType) {
            'integer', 'bigint', 'mediumint', 'smallint', 'tinyint' => $this->integer($column, $value),
            'float', 'double', 'decimal', 'real' => $this->float($column, $value),
            'boolean', 'bool' => $this->boolean($column, $value),
            'json' => $this->json($column, $value),
            'date' => $this->date($column, $value, 'Y-m-d'),
            'datetime', 'timestamp' => $this->date($column, $value, 'Y-m-d H:i:s'),
            'time' => $this->date($column, $value, 'H:i:s'),
            'string', 'text', 'mediumtext', 'longtext', 'char', 'varchar', 'uuid', 'ulid' => $this->string($value),
            default => is_array($value) ? json_encode($value) : $value,
        };
    }

    public function metaValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }

    private function integer(string $column, mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Column {$column} expects an integer value.");
        }

        return (int) $value;
    }

    private function float(string $column, mixed $value): float
    {
        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Column {$column} expects a numeric value.");
        }

        return (float) $value;
    }

    private function boolean(string $column, mixed $value): bool
    {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized === null) {
            throw new \InvalidArgumentException("Column {$column} expects a boolean value.");
        }

        return $normalized;
    }

    private function json(string $column, mixed $value): string
    {
        if (is_string($value)) {
            json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException("Column {$column} expects valid JSON.");
            }

            return $value;
        }

        return json_encode($value);
    }

    private function date(string $column, mixed $value, string $format): string
    {
        try {
            return Carbon::parse($value)->format($format);
        } catch (Throwable) {
            throw new \InvalidArgumentException("Column {$column} has an invalid date/time value.");
        }
    }

    private function string(mixed $value): string
    {
        return is_array($value) ? json_encode($value) : (string) $value;
    }
}
