<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleHasNavigation extends Model
{
    use HasFactory;

    protected $table = 'module_has_navigations';

    protected $fillable = [
        'module_id',
        'navigation_id',
        'label',
        'icon',
        'sort_order',
        'visible',
        'enabled',
    ];
}
