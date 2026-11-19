<?php

namespace Iquesters\Foundation\Support\BusinessEntity\Relations;

use InvalidArgumentException;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationType as R;

/**
 * The relation types business entity links can use: Laravel's relations that
 * map onto stored columns. Modules add their own by binding a RelationType
 * tagged "business-entity.relation-types", or with register().
 *
 * Not offered, because there is nothing to store for them:
 * - has_one_through / has_many_through: read paths over other links; chain
 *   two links instead;
 * - morph_to: the target table varies per row, so it has no fixed fields.
 */
final class RelationTypes
{
    public const TAG = 'business-entity.relation-types';

    /** @var array<string, R> */
    private array $types = [];

    /** @param iterable<R> $extra */
    public function __construct(iterable $extra = [])
    {
        foreach (self::builtIn() as $type) {
            $this->register($type);
        }

        foreach ($extra as $type) {
            $this->register($type);
        }
    }

    /** @return list<R> */
    public static function builtIn(): array
    {
        return [
            new StandardRelationType('has_one', 'has one', R::HOLDER_SELF, R::ONE, [R::COMPOSE]),
            new StandardRelationType('belongs_to', 'belongs to', R::HOLDER_PARENT, R::ONE, [R::COMPOSE, R::REFERENCE]),
            new StandardRelationType('morph_one', 'has one (polymorphic)', R::HOLDER_SELF, R::ONE, [R::COMPOSE], 'parent'),
            new StandardRelationType('has_many', 'has many', R::HOLDER_SELF, R::MANY, [R::COMPOSE]),
            new StandardRelationType('morph_many', 'has many (polymorphic)', R::HOLDER_SELF, R::MANY, [R::COMPOSE], 'parent'),
            new StandardRelationType('belongs_to_many', 'belongs to many', R::HOLDER_PIVOT, R::MANY, [R::REFERENCE]),
            new StandardRelationType('morph_to_many', 'belongs to many (polymorphic)', R::HOLDER_PIVOT, R::MANY, [R::REFERENCE], 'parent'),
            new StandardRelationType('morphed_by_many', 'is assigned to many (polymorphic, inverse)', R::HOLDER_PIVOT, R::MANY, [R::REFERENCE], 'related'),
        ];
    }

    public function register(R $type): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $type->name())) {
            throw new InvalidArgumentException("Invalid relation type name {$type->name()}.");
        }

        $valid = in_array($type->holder(), [R::HOLDER_SELF, R::HOLDER_PARENT, R::HOLDER_PIVOT], true)
            && in_array($type->cardinality(), [R::ONE, R::MANY], true)
            && $type->modes() !== []
            && array_diff($type->modes(), [R::COMPOSE, R::REFERENCE]) === []
            && in_array($type->morph(), [null, 'parent', 'related'], true)
            // Combinations the engine can store:
            && ! ($type->holder() === R::HOLDER_PIVOT && in_array(R::COMPOSE, $type->modes(), true))
            && ! ($type->holder() === R::HOLDER_SELF && in_array(R::REFERENCE, $type->modes(), true))
            && ! ($type->holder() === R::HOLDER_PARENT && ($type->cardinality() === R::MANY || $type->morph() !== null))
            && ! ($type->morph() === 'related' && $type->holder() !== R::HOLDER_PIVOT);

        if (! $valid) {
            throw new InvalidArgumentException("Relation type {$type->name()} has an unsupported combination of properties.");
        }

        $this->types[$type->name()] = $type;
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): R
    {
        return $this->types[$name] ?? throw new InvalidArgumentException("Unknown relation type {$name}.");
    }

    /** @return array<string, R> */
    public function all(): array
    {
        return $this->types;
    }

    /** For the builder: [{name, label, holder, morph, cardinality, modes}]. */
    public function describe(): array
    {
        return array_values(array_map(fn (R $t) => [
            'name' => $t->name(),
            'label' => $t->label(),
            'holder' => $t->holder(),
            'morph' => $t->morph(),
            'cardinality' => $t->cardinality(),
            'modes' => $t->modes(),
        ], $this->types));
    }
}
