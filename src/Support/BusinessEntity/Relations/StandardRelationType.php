<?php

namespace Iquesters\Foundation\Support\BusinessEntity\Relations;

/** A relation type defined by its properties (see RelationType). */
final class StandardRelationType implements RelationType
{
    /** @param list<string> $modes */
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly string $holder,
        private readonly string $cardinality,
        private readonly array $modes,
        private readonly ?string $morph = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function holder(): string
    {
        return $this->holder;
    }

    public function morph(): ?string
    {
        return $this->morph;
    }

    public function cardinality(): string
    {
        return $this->cardinality;
    }

    public function modes(): array
    {
        return $this->modes;
    }
}
