<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessEntity extends Model
{
    use HasFactory;

    protected $table = 'business_entities';

    protected $fillable = [
        'uid',
        'ref_module',
        'business_entity_name',
        'slug',
        'desc',
        'field_mapping',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'uid' => 'string',
        'field_mapping' => 'array',
    ];

    public function metas(): HasMany
    {
        return $this->hasMany(BusinessEntityMeta::class, 'ref_parent');
    }
}
