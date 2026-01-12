<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLogMeta extends Model
{
    use HasFactory;

    protected $table = 'api_log_metas';

    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the parent API log.
     */
    public function log()
    {
        return $this->belongsTo(ApiLog::class, 'ref_parent');
    }
}