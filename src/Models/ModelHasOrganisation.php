<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelHasOrganisation extends Model
{
    protected $table = 'model_has_organisations';

    protected $fillable = [
        'organisation_id',
        'model_type',
        'model_id',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }
}