<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory;

    protected $table = 'modules';

    protected $fillable = [
        'uid',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'uid' => 'string',
    ];

    public function metas(): HasMany
    {
        return $this->hasMany(ModuleMeta::class, 'ref_parent');
    }


    public function getMeta(string $key)
    {
        $meta = $this->metas()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : null;
    }

    public function setMeta(string $key, $value)
    {
        return $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value]
        );
    }
}