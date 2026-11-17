<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Iquesters\Foundation\Support\UserModel;

class MasterDataMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $table = 'master_data_metas';

    public function refCreatedBy()
    {
        return $this->belongsTo(UserModel::name(), 'created_by');
    }

    public function refUpdatedBy()
    {
        return $this->belongsTo(UserModel::name(), 'updated_by');
    }
}
