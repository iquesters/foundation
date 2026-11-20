<?php

namespace Iquesters\Foundation\Support\BusinessEntity;

use InvalidArgumentException;

/**
 * A definition that doesn't match the database. Besides the message, it
 * lists the pivot tables a many-to-many link needs but that don't exist
 * yet, so the builder can offer to create them as entities.
 */
final class InvalidBusinessEntityDefinition extends InvalidArgumentException
{
    /**
     * @param  string[]  $problems
     * @param  list<array{part: string, table: string, columns: string[]}>  $missingPivots
     */
    public function __construct(public readonly array $problems, public readonly array $missingPivots = [])
    {
        parent::__construct(implode(' ', $problems));
    }
}
