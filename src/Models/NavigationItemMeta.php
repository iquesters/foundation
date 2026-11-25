<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationItemMeta extends Model
{
    protected $table = 'navigation_item_metas';

    protected $fillable = ['ref_parent', 'meta_key', 'meta_value', 'status', 'created_by', 'updated_by'];
}
