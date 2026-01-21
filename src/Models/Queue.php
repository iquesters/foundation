<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Queue extends Model
{
    protected $fillable = [
        'uid',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the queue metas for the queue.
     */
    public function metas(): HasMany
    {
        return $this->hasMany(QueueMeta::class, 'ref_parent');
    }

    /**
     * Get active queue metas
     */
    public function activeMetas(): HasMany
    {
        return $this->metas()->where('status', 'active');
    }

    /**
     * Get meta value by key
     */
    public function getMetaValue(string $key, mixed $default = null): mixed
    {
        $meta = $this->activeMetas()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Set meta value
     */
    public function setMetaValue(string $key, mixed $value): void
    {
        $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            [
                'meta_value' => $value,
                'status' => 'active',
                'updated_by' => auth()->id() ?? 0,
            ]
        );
    }

    /**
     * Scope to get only active queues
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}