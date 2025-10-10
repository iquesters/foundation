<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    use HasFactory;

    protected $table = 'entities';

    protected $fillable = [
        'uid',
        'ref_module',
        'entity_name',
        'fields',
        'meta_fields',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'uid' => 'string',
        'fields' => 'array',
        'meta_fields' => 'array',
    ];

    /**
     * Relationship: Entity has many EntityMeta records
     */
    public function metas(): HasMany
    {
        return $this->hasMany(EntityMeta::class, 'ref_parent');
    }

    /**
     * Retrieve a specific meta value by key.
     */
    public function getMeta(string $key)
    {
        return optional(
            $this->metas()->where('meta_key', $key)->first()
        )->meta_value;
    }

    /**
     * Create or update a meta value by key.
     */
    public function setMeta(string $key, $value)
    {
        return $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value]
        );
    }
}