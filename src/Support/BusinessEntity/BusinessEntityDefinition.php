<?php

namespace Iquesters\Foundation\Support\BusinessEntity;

use InvalidArgumentException;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationType;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationTypes;

/**
 * The structure of a business entity: a root entity plus linked parts, read
 * and written as one record. It is configuration only: records live in the
 * parts' own tables and in the link columns and pivot tables that already
 * exist; nothing here creates tables.
 *
 *     {
 *       "version": 2,
 *       "parts": [
 *         {"alias": "customer", "entity_uid": "01J…", "table": "customers",
 *          "fields": [{"field": "name"}], "meta_fields": [{"meta_key": "phone"}]},
 *         {"alias": "address", "entity_uid": "01J…", "table": "addresses",
 *          "link": {"type": "has_one", "to": "customer", "foreign_key": "customer_id"},
 *          "fields": [{"field": "city"}]},
 *         {"alias": "notes", "entity_uid": "01J…", "table": "customer_notes",
 *          "link": {"type": "has_many", "to": "customer", "foreign_key": "customer_id"},
 *          "fields": [{"field": "body"}]},
 *         {"alias": "company", "entity_uid": "01J…", "table": "companies",
 *          "link": {"type": "belongs_to", "to": "customer", "mode": "reference",
 *                   "foreign_key": "company_id", "label_column": "name"}},
 *         {"alias": "roles", "model": "role", "table": "roles",
 *          "link": {"type": "morph_to_many", "to": "customer",
 *                   "pivot": {"table": "model_has_roles", "foreign_pivot_key": "model_id",
 *                             "related_pivot_key": "role_id", "morph_type": "model_type"}}}
 *       ]
 *     }
 *
 * Parts:
 * - The first part is the root: an entity, composed; its row's uid is the
 *   business record's uid.
 * - Every other part links to an earlier "tree" part (the root, or a part
 *   that is one row and composed). Its link type is a RelationType (has_one,
 *   belongs_to, morph_one, has_many, morph_many, belongs_to_many,
 *   morph_to_many, morphed_by_many, or one a module registered).
 * - Source: an entity (entity_uid, or "entity": its table name, which
 *   definition files use because uids differ per install), or a platform
 *   model ("model": a BusinessEntityModels key), which can only be referenced.
 * - Mode: compose, where rows are created and edited inside the record, or
 *   reference, where the record points at rows that already exist (they are
 *   picked in the form and have no fields of their own).
 *
 * Payload and response keys:
 * - Fields of tree parts: "{alias}__{field}" (flat, so the form engine can
 *   use them as field ids); meta under "meta".
 * - A part with a list of rows (many, compose): "{alias}" with a list of
 *   {uid?, field: value, meta: {key: value}}.
 * - A reference: "{alias}" with the picked uid (or id), or a list of them.
 *
 * Version 1 definitions (has_one / belongs_to only) are read unchanged.
 *
 * This class checks the structure only; BusinessEntityDefinitionValidator
 * checks it against the registered entities and the database.
 */
final class BusinessEntityDefinition
{
    public const VERSION = 2;

    public const HAS_ONE = 'has_one';

    public const BELONGS_TO = 'belongs_to';

    public const KEY_SEPARATOR = '__';

    /** Columns that are never selectable: managed by the entity API. */
    public const SYSTEM_COLUMNS = [
        'id', 'uid', 'status', 'created_by', 'updated_by', 'deleted_by',
        'created_at', 'updated_at', 'deleted_at',
    ];

    /** Response keys a leaf part's alias would collide with. */
    public const RESERVED_ALIASES = ['id', 'uid', 'status', 'meta', 'parts', 'detail_summary', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    private const ALIAS_PATTERN = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/';

    private const NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** @param array<string, array> $parts keyed by alias, root first */
    private function __construct(private readonly array $parts, private readonly RelationTypes $types) {}

    /** @throws InvalidArgumentException with every problem found */
    public static function fromArray(array $data, ?RelationTypes $types = null): self
    {
        $types ??= self::defaultTypes();
        $version = $data['version'] ?? 1;

        if (! in_array($version, [1, 2], true)) {
            throw new InvalidArgumentException("Unsupported business entity definition version {$version}.");
        }

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

            if (in_array($alias, self::RESERVED_ALIASES, true)) {
                $errors[] = "{$label}: \"{$alias}\" is reserved, choose another alias.";

                continue;
            }

            $part = [
                'alias' => $alias,
                'entity_uid' => self::str($raw['entity_uid'] ?? null),
                'entity' => self::str($raw['entity'] ?? null),
                'model' => self::str($raw['model'] ?? null),
                'table' => self::str($raw['table'] ?? null) ?? '',
                'label' => self::str($raw['label'] ?? null),
                'link' => null,
                'fields' => [],
                'meta_fields' => [],
            ];

            $sources = count(array_filter([$part['entity_uid'] ?? $part['entity'], $part['model']]));
            if ($sources !== 1) {
                $errors[] = "{$label}: choose either an entity (entity_uid or entity) or a model.";
            }

            if ($index === 0) {
                if (! empty($raw['link'])) {
                    $errors[] = "{$label}: the root part (the first one) cannot have a link.";
                }

                if ($part['model'] !== null) {
                    $errors[] = "{$label}: the root part must be an entity, not a model.";
                }
            } else {
                $part['link'] = self::parseLink($raw['link'] ?? null, $label, $parts, $types, $errors);
            }

            $part['fields'] = self::parseFields($raw['fields'] ?? [], 'field', $label, $errors);
            $part['meta_fields'] = self::parseFields($raw['meta_fields'] ?? [], 'meta_key', $label, $errors);

            foreach ($part['fields'] as $field) {
                if (in_array(strtolower($field['field']), self::SYSTEM_COLUMNS, true)) {
                    $errors[] = "{$label}: \"{$field['field']}\" is a system column and cannot be selected.";
                }
            }

            if (($part['link']['mode'] ?? null) === RelationType::REFERENCE) {
                if ($part['fields'] !== [] || $part['meta_fields'] !== []) {
                    $errors[] = "{$label}: a reference only points at existing records, so it has no fields to select.";
                }
            } elseif ($part['model'] !== null && $part['link'] !== null) {
                $errors[] = "{$label}: a model can only be referenced (mode \"reference\").";
            }

            $parts[$alias] = $part;
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $definition = new self($parts, $types);

        // Link columns are filled in automatically, so they can't also be form fields.
        foreach ($parts as $alias => $part) {
            foreach ($definition->lockedColumns($alias) as $column) {
                if (in_array($column, array_column($part['fields'], 'field'), true)) {
                    $errors[] = "Part \"{$alias}\": \"{$column}\" is filled in by a link, remove it from the selected fields.";
                }
            }
        }

        $selected = array_sum(array_map(fn ($p) => count($p['fields']) + count($p['meta_fields']), $parts));
        if ($errors === [] && $selected === 0 && $definition->leafAliases() === []) {
            $errors[] = 'Select at least one field.';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return $definition;
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

    /** The link's relation type, null for the root. */
    public function type(string $alias): ?RelationType
    {
        $link = $this->part($alias)['link'];

        return $link ? $this->types->get($link['type']) : null;
    }

    /** The root, or a part that is one row and composed: its fields are flat form fields and other parts can link to it. */
    public function isTree(string $alias): bool
    {
        $type = $this->type($alias);

        return $type === null || ($type->cardinality() === RelationType::ONE && $this->part($alias)['link']['mode'] === RelationType::COMPOSE);
    }

    public function isReference(string $alias): bool
    {
        return ($this->part($alias)['link']['mode'] ?? null) === RelationType::REFERENCE;
    }

    /** A composed list of rows (has_many, morph_many). */
    public function isRows(string $alias): bool
    {
        return ! $this->isTree($alias) && ! $this->isReference($alias);
    }

    /** @return string[] */
    public function treeAliases(): array
    {
        return array_values(array_filter(array_keys($this->parts), fn ($a) => $this->isTree($a)));
    }

    /** Parts sent and returned under their alias: lists of rows and references. @return string[] */
    public function leafAliases(): array
    {
        return array_values(array_filter(array_keys($this->parts), fn ($a) => ! $this->isTree($a)));
    }

    /** Tables whose rows the record creates and edits (references are only read). @return string[] */
    public function tables(): array
    {
        $tables = [];

        foreach ($this->parts as $alias => $part) {
            if (! $this->isReference($alias)) {
                $tables[] = $part['table'];
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * Tree aliases in the order records must be written: a belongs_to part
     * before the part that points at it, a has_one part after the part it
     * points at.
     *
     * @return string[]
     */
    public function writeOrder(): array
    {
        $after = []; // alias => aliases that must be written first

        foreach ($this->treeAliases() as $alias) {
            $after[$alias] ??= [];
            $link = $this->parts[$alias]['link'];

            if ($link === null) {
                continue;
            }

            if ($this->type($alias)->holder() === RelationType::HOLDER_SELF) {
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

        foreach ($this->treeAliases() as $alias) {
            $visit($alias);
        }

        return $order;
    }

    /**
     * Foreign-key columns in this tree part's table that tree links fill in:
     * [column => alias of the part whose id goes there].
     *
     * @return array<string, string>
     */
    public function foreignKeys(string $alias): array
    {
        $keys = [];

        foreach ($this->treeAliases() as $other) {
            $link = $this->parts[$other]['link'];

            if ($link === null) {
                continue;
            }

            $holder = $this->type($other)->holder();

            if ($holder === RelationType::HOLDER_SELF && $other === $alias) {
                $keys[$link['foreign_key']] = $link['to'];
            } elseif ($holder === RelationType::HOLDER_PARENT && $link['to'] === $alias) {
                $keys[$link['foreign_key']] = $other;
            }
        }

        return $keys;
    }

    /**
     * Morph type columns of this part's own table and their value (the
     * parent's morph class): morph_one / morph_many parts.
     *
     * @return array<string, string>
     */
    public function morphColumns(string $alias): array
    {
        $link = $this->part($alias)['link'];
        $type = $this->type($alias);

        if (! $link || $type->holder() !== RelationType::HOLDER_SELF || $type->morph() === null) {
            return [];
        }

        return [$link['morph_type'] => (string) $link['morph_class']];
    }

    /**
     * Every column of this part's table that a link fills in, so it can't be
     * a selected field: tree foreign keys, its own link column and morph type
     * (lists of rows), and belongs_to references stored on it.
     *
     * @return string[]
     */
    public function lockedColumns(string $alias): array
    {
        $columns = array_keys($this->isTree($alias) ? $this->foreignKeys($alias) : []);
        $link = $this->part($alias)['link'];

        if ($link && $this->type($alias)->holder() === RelationType::HOLDER_SELF) {
            $columns[] = $link['foreign_key'];
            $columns = array_merge($columns, array_keys($this->morphColumns($alias)));
        }

        foreach ($this->parts as $other => $part) {
            if ($part['link'] && $part['link']['to'] === $alias && $this->isReference($other)
                && $this->type($other)->holder() === RelationType::HOLDER_PARENT) {
                $columns[] = $part['link']['foreign_key'];
            }
        }

        return array_values(array_unique($columns));
    }

    public static function fieldKey(string $alias, string $name): string
    {
        return $alias.self::KEY_SEPARATOR.$name;
    }

    /**
     * Every selected field of the tree parts (the flat form fields), in part order.
     *
     * @return list<array{key: string, alias: string, name: string, meta: bool, label: ?string}>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->treeAliases() as $alias) {
            $part = $this->parts[$alias];

            foreach ($part['fields'] as $field) {
                $fields[] = ['key' => self::fieldKey($alias, $field['field']), 'alias' => $alias, 'name' => $field['field'], 'meta' => false, 'label' => $field['label']];
            }

            foreach ($part['meta_fields'] as $field) {
                $fields[] = ['key' => self::fieldKey($alias, $field['meta_key']), 'alias' => $alias, 'name' => $field['meta_key'], 'meta' => true, 'label' => $field['label']];
            }
        }

        return $fields;
    }

    /**
     * Same definition with part keys replaced, e.g. the table and entity_uid
     * resolved from the entity, or link defaults (morph_class, label_column).
     * "link" values are merged into the link.
     *
     * @param  array<string, array>  $patches  alias => keys
     */
    public function withPartData(array $patches): self
    {
        $parts = $this->parts;

        foreach ($patches as $alias => $patch) {
            if (! isset($parts[$alias])) {
                continue;
            }

            if (isset($patch['link']) && $parts[$alias]['link'] !== null) {
                $parts[$alias]['link'] = array_merge($parts[$alias]['link'], $patch['link']);
                if (isset($patch['link']['pivot']) && isset($this->parts[$alias]['link']['pivot'])) {
                    $parts[$alias]['link']['pivot'] = array_merge($this->parts[$alias]['link']['pivot'], $patch['link']['pivot']);
                }
            }

            unset($patch['link']);
            $parts[$alias] = array_merge($parts[$alias], $patch);
        }

        return new self($parts, $this->types);
    }

    /** Same definition with each part's table replaced (resolved from its entity). */
    public function withTables(array $tablesByAlias): self
    {
        return $this->withPartData(array_map(fn ($table) => ['table' => (string) $table], $tablesByAlias));
    }

    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'parts' => array_values(array_map(function (array $part) {
                $out = ['alias' => $part['alias']];

                foreach (['entity_uid', 'entity', 'model'] as $key) {
                    if ($part[$key] !== null) {
                        $out[$key] = $part[$key];
                    }
                }

                $out['table'] = $part['table'];

                if ($part['label'] !== null) {
                    $out['label'] = $part['label'];
                }

                if ($part['link'] !== null) {
                    $out['link'] = self::withoutNulls($part['link']);
                }

                $out['fields'] = array_map(fn ($f) => self::withoutNulls($f), $part['fields']);
                $out['meta_fields'] = array_map(fn ($f) => self::withoutNulls($f), $part['meta_fields']);

                return $out;
            }, $this->parts)),
        ];
    }

    /** A portable copy for definition files: entities named by table instead of uid. */
    public function toPortableArray(): array
    {
        $data = $this->toArray();

        foreach ($data['parts'] as &$part) {
            if (isset($part['entity_uid'])) {
                $part['entity'] = $part['table'];
                unset($part['entity_uid']);
            }
        }

        return $data;
    }

    private static function parseLink(mixed $raw, string $label, array $earlierParts, RelationTypes $types, array &$errors): ?array
    {
        if (! is_array($raw)) {
            $errors[] = "{$label}: a link to an earlier part is required (every part except the root needs one).";

            return null;
        }

        $typeName = $raw['type'] ?? null;
        $to = $raw['to'] ?? null;

        if (! is_string($typeName) || ! $types->has($typeName)) {
            $errors[] = "{$label}: link type must be one of ".implode(', ', array_keys($types->all())).'.';

            return null;
        }

        $type = $types->get($typeName);
        $mode = $raw['mode'] ?? $type->modes()[0];

        if (! in_array($mode, $type->modes(), true)) {
            $errors[] = "{$label}: a {$typeName} link can only be ".implode(' or ', $type->modes()).'.';
        }

        if (! is_string($to) || ! isset($earlierParts[$to])) {
            $errors[] = "{$label}: link must point to an earlier part.";
        } elseif (! self::rawIsTree($earlierParts[$to], $types)) {
            $errors[] = "{$label}: link must point to the root or to a part that is one composed row (not a list or a reference).";
        }

        $link = ['type' => $typeName, 'to' => $to, 'mode' => $mode];

        if ($type->holder() === RelationType::HOLDER_PIVOT) {
            $pivot = is_array($raw['pivot'] ?? null) ? $raw['pivot'] : [];
            $link['pivot'] = [
                'table' => self::column($pivot['table'] ?? null, "{$label}: pivot table", $errors),
                'foreign_pivot_key' => self::column($pivot['foreign_pivot_key'] ?? null, "{$label}: pivot foreign_pivot_key", $errors),
                'related_pivot_key' => self::column($pivot['related_pivot_key'] ?? null, "{$label}: pivot related_pivot_key", $errors),
            ];

            if ($type->morph() !== null) {
                $link['pivot']['morph_type'] = self::column($pivot['morph_type'] ?? null, "{$label}: pivot morph_type", $errors);
            }
        } else {
            $link['foreign_key'] = self::column($raw['foreign_key'] ?? null, "{$label}: link foreign_key", $errors);

            if ($type->morph() !== null) {
                $link['morph_type'] = self::column($raw['morph_type'] ?? null, "{$label}: link morph_type", $errors);
            }
        }

        if ($type->morph() !== null) {
            $link['morph_class'] = self::str($raw['morph_class'] ?? null);
        }

        if ($mode === RelationType::REFERENCE) {
            $link['label_column'] = isset($raw['label_column'])
                ? self::column($raw['label_column'], "{$label}: label_column", $errors)
                : null;
        }

        return $link;
    }

    /** isTree() on a raw part while parsing. */
    private static function rawIsTree(array $part, RelationTypes $types): bool
    {
        if ($part['link'] === null) {
            return true;
        }

        return $types->has($part['link']['type'])
            && $types->get($part['link']['type'])->cardinality() === RelationType::ONE
            && $part['link']['mode'] === RelationType::COMPOSE;
    }

    private static function column(mixed $value, string $what, array &$errors): ?string
    {
        if (! is_string($value) || ! preg_match(self::NAME_PATTERN, $value)) {
            $errors[] = "{$what} must be a column or table name.";

            return null;
        }

        return $value;
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
            $fields[] = [$nameKey => $name, 'label' => is_array($field) ? self::str($field['label'] ?? null) : null];
        }

        return $fields;
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function withoutNulls(array $values): array
    {
        return array_filter(
            array_map(fn ($v) => is_array($v) ? self::withoutNulls($v) : $v, $values),
            fn ($v) => $v !== null
        );
    }

    private static function defaultTypes(): RelationTypes
    {
        return function_exists('app') && app()->bound(RelationTypes::class)
            ? app(RelationTypes::class)
            : new RelationTypes;
    }
}
