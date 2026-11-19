<?php

namespace Iquesters\Foundation\Support\BusinessEntity;

use InvalidArgumentException;

/**
 * The structure of a business entity: a root entity table plus linked parts,
 * saved and read as one record.
 *
 *     {
 *       "version": 1,
 *       "parts": [
 *         {"alias": "customer", "entity_uid": "01J…", "table": "customers",
 *          "fields": [{"field": "name", "label": "Name"}],
 *          "meta_fields": [{"meta_key": "phone"}]},
 *         {"alias": "address", "entity_uid": "01J…", "table": "addresses",
 *          "link": {"type": "has_one", "to": "customer", "foreign_key": "customer_id"},
 *          "fields": [{"field": "city"}]}
 *       ]
 *     }
 *
 * - The first part is the root: its record's uid is the business record's uid.
 * - Every other part links to an earlier part:
 *     has_one    this part's table holds foreign_key → the "to" part's id
 *                (written after "to"; deleted with the business record);
 *     belongs_to the "to" part's table holds foreign_key → this part's id
 *                (written before "to"; shared, so never deleted with it).
 * - Fields are addressed in payloads and responses as "{alias}__{field}"
 *   (flat, so the form engine can use them as field ids; a "." would become
 *   "_" in PHP form data).
 *
 * This class checks the structure only; BusinessEntityDefinitionValidator
 * checks it against the registered entities and their tables.
 */
final class BusinessEntityDefinition
{
    public const VERSION = 1;

    public const HAS_ONE = 'has_one';

    public const BELONGS_TO = 'belongs_to';

    public const LINK_TYPES = [self::HAS_ONE, self::BELONGS_TO];

    public const KEY_SEPARATOR = '__';

    /** Columns that are never selectable: managed by the entity API. */
    public const SYSTEM_COLUMNS = [
        'id', 'uid', 'status', 'created_by', 'updated_by', 'deleted_by',
        'created_at', 'updated_at', 'deleted_at',
    ];

    private const ALIAS_PATTERN = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/';

    private const NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** @param array<string, array> $parts keyed by alias, root first */
    private function __construct(private readonly array $parts) {}

    /** @throws InvalidArgumentException with every problem found */
    public static function fromArray(array $data): self
    {
        $errors = [];
        $parts = [];
        $rawParts = $data['parts'] ?? null;

        if (! is_array($rawParts) || ! array_is_list($rawParts) || $rawParts === []) {
            throw new InvalidArgumentException('A business entity needs at least one part (its root entity).');
        }

        foreach ($rawParts as $index => $raw) {
            $label = 'Part '.($index + 1);

            if (! is_array($raw)) {
                $errors[] = "{$label} is not an object.";

                continue;
            }

            $alias = is_string($raw['alias'] ?? null) ? $raw['alias'] : '';

            if (! preg_match(self::ALIAS_PATTERN, $alias) || strlen($alias) > 40) {
                $errors[] = "{$label}: alias must be lowercase letters, digits and single underscores, starting with a letter (max 40).";

                continue;
            }

            $label = "Part \"{$alias}\"";

            if (isset($parts[$alias])) {
                $errors[] = "{$label}: alias is used twice.";

                continue;
            }

            $part = [
                'alias' => $alias,
                'entity_uid' => is_string($raw['entity_uid'] ?? null) ? $raw['entity_uid'] : '',
                'table' => is_string($raw['table'] ?? null) ? $raw['table'] : '',
                'label' => is_string($raw['label'] ?? null) && $raw['label'] !== '' ? $raw['label'] : null,
                'link' => null,
                'fields' => [],
                'meta_fields' => [],
            ];

            if ($part['entity_uid'] === '') {
                $errors[] = "{$label}: entity_uid is required.";
            }

            if ($index === 0) {
                if (! empty($raw['link'])) {
                    $errors[] = "{$label}: the root part (the first one) cannot have a link.";
                }
            } else {
                $link = self::parseLink($raw['link'] ?? null, $label, $parts, $errors);
                $part['link'] = $link;
            }

            $part['fields'] = self::parseFields($raw['fields'] ?? [], 'field', $label, $errors);
            $part['meta_fields'] = self::parseFields($raw['meta_fields'] ?? [], 'meta_key', $label, $errors);

            foreach ($part['fields'] as $field) {
                if (in_array(strtolower($field['field']), self::SYSTEM_COLUMNS, true)) {
                    $errors[] = "{$label}: \"{$field['field']}\" is a system column and cannot be selected.";
                }
            }

            $parts[$alias] = $part;
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        // Foreign keys are filled in automatically, so they can't also be form fields.
        foreach ($parts as $alias => $part) {
            foreach (array_keys(self::foreignKeysIn($parts, $alias)) as $column) {
                if (in_array($column, array_column($part['fields'], 'field'), true)) {
                    $errors[] = "Part \"{$alias}\": \"{$column}\" is filled in by its link, remove it from the selected fields.";
                }
            }
        }

        $selected = array_sum(array_map(fn ($p) => count($p['fields']) + count($p['meta_fields']), $parts));
        if ($errors === [] && $selected === 0) {
            $errors[] = 'Select at least one field.';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return new self($parts);
    }

    /** @return array<string, array> parts keyed by alias, root first */
    public function parts(): array
    {
        return $this->parts;
    }

    public function part(string $alias): array
    {
        return $this->parts[$alias] ?? throw new InvalidArgumentException("Unknown part {$alias}.");
    }

    public function rootAlias(): string
    {
        return array_key_first($this->parts);
    }

    public function root(): array
    {
        return $this->parts[$this->rootAlias()];
    }

    /** @return string[] */
    public function tables(): array
    {
        return array_values(array_unique(array_column($this->parts, 'table')));
    }

    /**
     * Aliases in the order records must be written: a belongs_to part before
     * the part that points at it, a has_one part after the part it points at.
     *
     * @return string[]
     */
    public function writeOrder(): array
    {
        $after = []; // alias => aliases that must be written first

        foreach ($this->parts as $alias => $part) {
            $after[$alias] ??= [];
            $link = $part['link'];

            if ($link === null) {
                continue;
            }

            if ($link['type'] === self::HAS_ONE) {
                $after[$alias][] = $link['to'];
            } else {
                $after[$link['to']][] = $alias;
            }
        }

        $order = [];
        $visit = function (string $alias) use (&$visit, &$order, $after): void {
            if (in_array($alias, $order, true)) {
                return;
            }

            foreach ($after[$alias] ?? [] as $first) {
                $visit($first);
            }

            $order[] = $alias;
        };

        foreach (array_keys($this->parts) as $alias) {
            $visit($alias);
        }

        return $order;
    }

    /**
     * Foreign-key columns in this part's table that the links fill in:
     * [column => alias of the part whose id goes there].
     *
     * @return array<string, string>
     */
    public function foreignKeys(string $alias): array
    {
        $keys = [];

        foreach ($this->parts as $other => $part) {
            $link = $part['link'];

            if ($link === null) {
                continue;
            }

            if ($link['type'] === self::HAS_ONE && $other === $alias) {
                $keys[$link['foreign_key']] = $link['to'];
            } elseif ($link['type'] === self::BELONGS_TO && $link['to'] === $alias) {
                $keys[$link['foreign_key']] = $other;
            }
        }

        return $keys;
    }

    public static function fieldKey(string $alias, string $name): string
    {
        return $alias.self::KEY_SEPARATOR.$name;
    }

    /**
     * Every selected field, in part order.
     *
     * @return list<array{key: string, alias: string, name: string, meta: bool, label: ?string}>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->parts as $alias => $part) {
            foreach ($part['fields'] as $field) {
                $fields[] = ['key' => self::fieldKey($alias, $field['field']), 'alias' => $alias, 'name' => $field['field'], 'meta' => false, 'label' => $field['label']];
            }

            foreach ($part['meta_fields'] as $field) {
                $fields[] = ['key' => self::fieldKey($alias, $field['meta_key']), 'alias' => $alias, 'name' => $field['meta_key'], 'meta' => true, 'label' => $field['label']];
            }
        }

        return $fields;
    }

    /** Same definition with each part's table replaced (resolved from its entity). */
    public function withTables(array $tablesByAlias): self
    {
        $parts = $this->parts;

        foreach ($tablesByAlias as $alias => $table) {
            if (isset($parts[$alias])) {
                $parts[$alias]['table'] = (string) $table;
            }
        }

        return new self($parts);
    }

    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'parts' => array_values(array_map(function (array $part) {
                $out = ['alias' => $part['alias'], 'entity_uid' => $part['entity_uid'], 'table' => $part['table']];

                if ($part['label'] !== null) {
                    $out['label'] = $part['label'];
                }

                if ($part['link'] !== null) {
                    $out['link'] = $part['link'];
                }

                $out['fields'] = array_map(fn ($f) => array_filter($f, fn ($v) => $v !== null), $part['fields']);
                $out['meta_fields'] = array_map(fn ($f) => array_filter($f, fn ($v) => $v !== null), $part['meta_fields']);

                return $out;
            }, $this->parts)),
        ];
    }

    private static function parseLink(mixed $raw, string $label, array $earlierParts, array &$errors): ?array
    {
        if (! is_array($raw)) {
            $errors[] = "{$label}: a link to an earlier part is required (every part except the root needs one).";

            return null;
        }

        $type = $raw['type'] ?? null;
        $to = $raw['to'] ?? null;
        $foreignKey = $raw['foreign_key'] ?? null;

        if (! in_array($type, self::LINK_TYPES, true)) {
            $errors[] = "{$label}: link type must be has_one or belongs_to.";
        }

        if (! is_string($to) || ! isset($earlierParts[$to])) {
            $errors[] = "{$label}: link must point to an earlier part.";
        }

        if (! is_string($foreignKey) || ! preg_match(self::NAME_PATTERN, $foreignKey)) {
            $errors[] = "{$label}: link foreign_key must be a column name.";
        }

        return ['type' => $type, 'to' => $to, 'foreign_key' => $foreignKey];
    }

    /** @return list<array{field?: string, meta_key?: string, label: ?string}> */
    private static function parseFields(mixed $raw, string $nameKey, string $label, array &$errors): array
    {
        if (! is_array($raw)) {
            $errors[] = "{$label}: {$nameKey}s must be a list.";

            return [];
        }

        $fields = [];
        $seen = [];

        foreach ($raw as $field) {
            $name = is_array($field) ? ($field[$nameKey] ?? null) : $field;

            if (! is_string($name) || ! preg_match(self::NAME_PATTERN, $name) || str_contains($name, self::KEY_SEPARATOR)) {
                $errors[] = "{$label}: invalid {$nameKey} \"".(is_scalar($name) ? $name : '?').'".';

                continue;
            }

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $fieldLabel = is_array($field) && is_string($field['label'] ?? null) && $field['label'] !== '' ? $field['label'] : null;
            $fields[] = [$nameKey => $name, 'label' => $fieldLabel];
        }

        return $fields;
    }

    /** foreignKeys() on raw parts, used while parsing. */
    private static function foreignKeysIn(array $parts, string $alias): array
    {
        return (new self($parts))->foreignKeys($alias);
    }
}
