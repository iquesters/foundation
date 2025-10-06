<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModuleMeta extends Model
{
    use HasFactory;

    protected $table = 'module_metas';

    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by',
    ];
}