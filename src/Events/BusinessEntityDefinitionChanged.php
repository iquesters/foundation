<?php

namespace Iquesters\Foundation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Iquesters\Foundation\Models\BusinessEntity;

/**
 * A business entity definition was created, updated or deleted (from the
 * admin builder or business-entity:sync), after its schemas were generated.
 */
final class BusinessEntityDefinitionChanged
{
    use Dispatchable;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public function __construct(
        public readonly BusinessEntity $businessEntity,
        public readonly string $action,
    ) {}
}
