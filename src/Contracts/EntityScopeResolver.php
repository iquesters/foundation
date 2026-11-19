<?php

namespace Iquesters\Foundation\Contracts;

interface EntityScopeResolver
{
    /**
     * Resolve the IDs the current actor may access for a given scope column
     * (e.g. 'organisation_id') on a dynamic entity table.
     *
     * Return null if this resolver has no opinion on the given column, so
     * EntityScope can ask the next registered resolver. Return an
     * array (possibly empty) once a resolver claims the column — an empty
     * array means the actor has no accessible IDs for it.
     *
     * @return array<int>|null
     */
    public function resolveAllowedIds(string $column): ?array;
}
