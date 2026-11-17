<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Navigation extends Model
{
    use HasFactory;

    protected $table = 'navigations';

    protected $fillable = [
        'uid',
        'ref_parent',
        'name',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'uid' => 'string',
    ];

    public function metas(): HasMany
    {
        return $this->hasMany(NavigationMeta::class, 'ref_parent');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'ref_parent');
    }

    public function getMeta(string $key)
    {
        $meta = $this->relationLoaded('metas')
            ? $this->metas->firstWhere('meta_key', $key)
            : $this->metas()->where('meta_key', $key)->first();

        return $meta ? $meta->meta_value : null;
    }

    public function setMeta(string $key, $value)
    {
        $this->unsetRelation('metas');

        return $this->metas()->updateOrCreate(
            [
                'ref_parent' => $this->id,
                'meta_key' => $key,
            ],
            [
                'meta_value' => $value,
            ]
        );
    }
}
