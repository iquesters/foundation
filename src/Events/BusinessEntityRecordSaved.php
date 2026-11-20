<?php

namespace Iquesters\Foundation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;

/**
 * A business entity record was created, updated or deleted through the
 * business entity API (after the transaction committed). $record is the
 * record as the API returns it.
 */
final class BusinessEntityRecordSaved
{
    use Dispatchable;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public function __construct(
        public readonly BusinessEntityDefinition $definition,
        public readonly object $record,
        public readonly string $action,
    ) {}
}
