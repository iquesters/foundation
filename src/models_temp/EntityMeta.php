<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityMeta extends Model
{
    use HasFactory;

    protected $table = 'entity_metas';

    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'ref_parent');
    }
}