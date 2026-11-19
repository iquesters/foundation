<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;

/**
 * A record made of several entity tables, e.g. a customer with its address.
 * The parts and their links are in `definition` (see BusinessEntityDefinition);
 * the generated form and table schema uids are metas (form_schema_uid,
 * table_schema_uid).
 */
class BusinessEntity extends Model
{
    protected $table = 'business_entities';

    protected $fillable = [
        'uid',
        'ref_module',
        'name',
        'slug',
        'desc',
        'definition',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'definition' => 'array',
    ];

    public function metas(): HasMany
    {
        return $this->hasMany(BusinessEntityMeta::class, 'ref_parent');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'ref_module');
    }

    /** Not soft-deleted (status "deleted"). */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('status', '<>', EntityStatus::DELETED);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EntityStatus::ACTIVE);
    }

    /** The parsed definition. Throws InvalidArgumentException when it is invalid. */
    public function parsedDefinition(): BusinessEntityDefinition
    {
        return BusinessEntityDefinition::fromArray(is_array($this->definition) ? $this->definition : []);
    }

    public function getMeta(string $key): ?string
    {
        $value = $this->metas()->where('meta_key', $key)->value('meta_value');

        return $value === null ? null : (string) $value;
    }

    public function setMeta(string $key, mixed $value): void
    {
        $exists = $this->metas()->where('meta_key', $key)->exists();
        $values = ['meta_value' => $value, 'status' => EntityStatus::ACTIVE, 'updated_by' => auth()->id()];

        if (! $exists) {
            $values['created_by'] = auth()->id();
        }

        $this->metas()->updateOrCreate(['meta_key' => $key], $values);
    }
}
