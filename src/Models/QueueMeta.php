<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueMeta extends Model
{
    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the queue that owns the meta.
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class, 'ref_parent');
    }

    /**
     * Scope to get only active metas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}