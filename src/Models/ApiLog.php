<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'api_logs';

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'uid',
        'ref_type',
        'ref_id',
        'endpoint_provider',
        'event',
        'direction',
        'endpoint',
        'ip_address',
        'start_ts',
        'end_ts',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'start_ts' => 'datetime',
        'end_ts'   => 'datetime',
    ];

    /**
     * Get all meta records associated with this log.
     */
    public function metas()
    {
        return $this->hasMany(ApiLogMeta::class, 'ref_parent');
    }

    /**
     * Retrieve a meta value by key.
     */
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->metas()
            ->where('meta_key', $key)
            ->first();

        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Create or update a meta value.
     */
    public function setMeta(string $key, $value)
    {
        return $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value]
        );
    }

    /**
     * Attach multiple meta values at once.
     */
    public function setMetas(array $metas): void
    {
        foreach ($metas as $key => $value) {
            $this->setMeta($key, $value);
        }
    }

    /**
     * Attach additional references.
     *
     * Example:
     * $log->attachRef('channel', $channel->uid);
     */
    public function attachRef(string $type, string $id): void
    {
        $this->setMeta("ref:{$type}", $id);
    }

    /**
     * Calculate execution duration in milliseconds.
     */
    public function durationMs(): ?int
    {
        if (!$this->start_ts || !$this->end_ts) {
            return null;
        }

        return $this->end_ts->diffInMilliseconds($this->start_ts);
    }
}