<?php

namespace Iquesters\Foundation\Services\Entity;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Builds Laravel validation rules from entity field definitions, so the generic
 * entity API enforces the same constraints the entity builder declares.
 *
 * Type checks (integer/decimal/boolean/date/json) are done by the controller's
 * normalizers; this class adds what they don't cover: required-on-create,
 * string length, email format, allowed options, uniqueness and numeric range.
 *
 * Field definition keys used: type, required, nullable, default, maxlength,
 * input_type, options, unique, min, max.
 */
class EntityValidator
{
    private const STRING_MAX = [
        'varchar' => 255, 'string' => 255, 'char' => 255,
        'text' => 65535, 'mediumtext' => 16777215,
    ];

    /**
     * @param  array  $mainData  main-table payload, keyed by column (writable columns only)
     * @param  array  $metaData  meta payload, keyed by meta_key
     * @param  int|null  $ignoreId  record id to ignore for unique checks (update)
     *
     * @throws ValidationException
     */
    public function validate(
        string $table,
        array $mainDefinitions,
        array $metaDefinitions,
        array $mainData,
        array $metaData,
        array $writableColumns,
        bool $isUpdate,
        ?int $ignoreId = null
    ): void {
        $rules = [];

        foreach ($writableColumns as $column) {
            if (isset($mainDefinitions[$column])) {
                $rules[$column] = $this->rulesFor($mainDefinitions[$column], $isUpdate, $table, $column, $ignoreId);
            }
        }

        foreach ($metaDefinitions as $key => $definition) {
            // Uniqueness is not supported on meta values (they live in a key/value table).
            unset($definition['unique']);
            $rules["meta.{$key}"] = $this->rulesFor($definition, $isUpdate);
        }

        $validator = Validator::make($mainData + ['meta' => $metaData], $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public function rulesFor(
        array $definition,
        bool $isUpdate,
        ?string $table = null,
        ?string $column = null,
        ?int $ignoreId = null
    ): array {
        $type = strtolower((string) ($definition['type'] ?? 'string'));
        $nullable = (bool) ($definition['nullable'] ?? true);
        $required = (bool) ($definition['required'] ?? false);
        $hasDefault = ($definition['default'] ?? null) !== null;
        $inputType = strtolower((string) ($definition['input_type'] ?? ''));

        $rules = [];

        if ($isUpdate) {
            // Partial updates: only validate what is sent, but never allow blanking a required field.
            $rules[] = 'sometimes';
            $rules[] = ($required || ! $nullable) ? 'required' : 'nullable';
        } elseif ($required || (! $nullable && ! $hasDefault)) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        if (isset(self::STRING_MAX[$type]) || in_array($type, ['longtext'], true)) {
            $max = self::STRING_MAX[$type] ?? null;
            $maxlength = isset($definition['maxlength']) && is_numeric($definition['maxlength'])
                ? (int) $definition['maxlength']
                : null;

            if ($maxlength !== null && $maxlength > 0) {
                $max = $max === null ? $maxlength : min($max, $maxlength);
            }

            if ($max !== null) {
                $rules[] = "max:{$max}";
            }
        }

        if ($inputType === 'email') {
            $rules[] = 'email';
        }

        if (in_array($type, ['integer', 'bigint', 'decimal', 'float', 'double'], true) || $inputType === 'number') {
            $rules[] = 'numeric';

            if (isset($definition['min']) && is_numeric($definition['min'])) {
                $rules[] = 'min:'.$definition['min'];
            }

            if (isset($definition['max']) && is_numeric($definition['max'])) {
                $rules[] = 'max:'.$definition['max'];
            }
        }

        $options = $this->optionValues($definition['options'] ?? null);
        if ($options !== []) {
            $rules[] = Rule::in($options);
        }

        if (! empty($definition['unique']) && $table && $column) {
            $unique = Rule::unique($table, $column);
            $rules[] = $ignoreId ? $unique->ignore($ignoreId) : $unique;
        }

        return $rules;
    }

    /**
     * Accepts ['a', 'b'], ['a' => 'Label A'], or [['value' => 'a', 'label' => 'A'], ...].
     */
    private function optionValues(mixed $options): array
    {
        if (! is_array($options) || $options === []) {
            return [];
        }

        $values = [];

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                if (array_key_exists('value', $option)) {
                    $values[] = (string) $option['value'];
                }
            } elseif (is_string($key)) {
                $values[] = $key;
            } else {
                $values[] = (string) $option;
            }
        }

        return $values;
    }
}
