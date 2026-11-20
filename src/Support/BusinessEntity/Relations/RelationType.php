<?php

namespace Iquesters\Foundation\Support\BusinessEntity\Relations;

/**
 * The shape of one kind of link between two business entity parts, matching
 * a Laravel relation. A link always goes from a part to an earlier part, the
 * "to" part ("parent" below); the part itself is the "related" side.
 *
 * The record engine runs a link from this description alone:
 *
 * - holder: where the link is stored
 *     self    this part's table holds foreign_key → parent id
 *             (has_one, has_many, morph_one, morph_many)
 *     parent  the parent's table holds foreign_key → this part's id (belongs_to)
 *     pivot   a pivot table holds foreign_pivot_key → parent id and
 *             related_pivot_key → this part's id
 *             (belongs_to_many, morph_to_many, morphed_by_many)
 * - morph: which side a morph type column describes
 *     null     no morph column
 *     parent   the type names the parent (morph_one, morph_many, morph_to_many)
 *     related  the type names this part (morphed_by_many)
 * - cardinality: one row or a list of rows per parent.
 * - modes: compose (the rows are created and edited inside the record) and/or
 *   reference (the record points at rows that already exist).
 *
 * A business entity never creates tables or columns: every column a link
 * uses must already exist (BusinessEntityDefinitionValidator checks it).
 */
interface RelationType
{
    public const HOLDER_SELF = 'self';

    public const HOLDER_PARENT = 'parent';

    public const HOLDER_PIVOT = 'pivot';

    public const ONE = 'one';

    public const MANY = 'many';

    public const COMPOSE = 'compose';

    public const REFERENCE = 'reference';

    /** Definition value, e.g. "has_many". */
    public function name(): string;

    /** Plain-language description for the builder, e.g. "has many". */
    public function label(): string;

    public function holder(): string;

    /** null, "parent" or "related". */
    public function morph(): ?string;

    public function cardinality(): string;

    /** @return list<string> supported modes, the default first */
    public function modes(): array;
}
