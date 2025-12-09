<?php

namespace Iquesters\Foundation\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Iquesters\Organisation\Models\Organisation;

trait HasOrganisations
{
    public function organisations(): MorphToMany
    {
        return $this->morphToMany(
            Organisation::class,
            'model',
            'model_has_organisations'
        );
    }

    public function assignOrganisation($organisation): self
    {
        if (is_string($organisation)) {
            $organisation = Organisation::where('uid', $organisation)->firstOrFail();
        }

        if (is_int($organisation)) {
            $organisation = Organisation::findOrFail($organisation);
        }

        $this->organisations()->syncWithoutDetaching([$organisation->id]);

        return $this;
    }

    public function removeOrganisation($organisation): self
    {
        if (is_string($organisation)) {
            $organisation = Organisation::where('uid', $organisation)->firstOrFail();
        }

        if (is_int($organisation)) {
            $organisation = Organisation::findOrFail($organisation);
        }

        $this->organisations()->detach($organisation->id);

        return $this;
    }

    public function hasOrganisation($organisation): bool
    {
        if (is_string($organisation)) {
            return $this->organisations()->where('uid', $organisation)->exists();
        }

        if (is_int($organisation)) {
            return $this->organisations()->where('id', $organisation)->exists();
        }

        if ($organisation instanceof Organisation) {
            return $this->organisations()->where('id', $organisation->id)->exists();
        }

        return false;
    }

    public function syncOrganisations(array $organisations): self
    {
        $organisationIds = [];
        
        foreach ($organisations as $organisation) {
            if (is_string($organisation)) {
                $org = Organisation::where('uid', $organisation)->firstOrFail();
                $organisationIds[] = $org->id;
            } elseif (is_int($organisation)) {
                $organisationIds[] = $organisation;
            } elseif ($organisation instanceof Organisation) {
                $organisationIds[] = $organisation->id;
            }
        }

        $this->organisations()->sync($organisationIds);

        return $this;
    }
}