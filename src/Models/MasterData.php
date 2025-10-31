<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Iquesters\Foundation\Constants\EntityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class MasterData extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'parent_id',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $table = 'master_data';

    public function parent()
    {
        return $this->belongsTo(MasterData::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MasterData::class, 'parent_id');
    }

    public function collect_children()
    {
        return $this->children()->where([
            'status' => EntityStatus::ACTIVE
        ]);
    }

    public function refCreatedBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function refUpdatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function metas(): HasMany
    {
        return $this->hasMany(MasterDataMeta::class, 'ref_parent');
    }

    public function setMetaBulk(array $metas, int $userId): void
    {
        foreach ($metas as $key => $value) {
            $this->setMetaValue($key, $value, $userId);
        }
    }

    public function setMetaValue(string $key, $value, int $userId): void
    {
        $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            [
                'meta_value' => $value,
                'status' => EntityStatus::ACTIVE,
                'created_by' => $userId,
                'updated_by' => $userId
            ]
        );
    }

    public function getMetaValue(string $key)
    {
        return $this->metas()
            ->where('meta_key', $key)
            ->where('status', EntityStatus::ACTIVE)
            ->value('meta_value');
    }
}