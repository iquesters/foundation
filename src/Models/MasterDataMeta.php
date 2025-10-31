<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class MasterDataMeta extends Model
{
    use HasFactory;
    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $table = 'master_data_metas';

    public function refCreatedBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function refUpdatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}