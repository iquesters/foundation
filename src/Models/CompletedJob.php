<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CompletedJob extends Model
{
    protected $table = 'completed_jobs';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'connection',
        'queue',
        'payload',
        'response',
        'queued_at',
        'available_at',
        'reserved_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'available_at' => 'datetime',
        'reserved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
            
            if (empty($job->completed_at)) {
                $job->completed_at = now();
            }
        });
    }

    /**
     * Get the decoded payload
     */
    public function getDecodedPayload(): array
    {
        return json_decode($this->payload, true) ?? [];
    }

    /**
     * Get the decoded response
     */
    public function getDecodedResponse(): array
    {
        return json_decode($this->response, true) ?? [];
    }

    /**
     * Get job class name from payload
     */
    public function getJobClassName(): ?string
    {
        $payload = $this->getDecodedPayload();
        
        if (isset($payload['displayName'])) {
            return $payload['displayName'];
        }
        
        if (isset($payload['data']['commandName'])) {
            return $payload['data']['commandName'];
        }
        
        return null;
    }

    /**
     * Scope to get jobs completed today
     */
    public function scopeCompletedToday($query)
    {
        return $query->whereDate('completed_at', today());
    }

    /**
     * Scope to get jobs for a specific queue
     */
    public function scopeForQueue($query, string $queueName)
    {
        return $query->where('queue', $queueName);
    }

    /**
     * Scope to get jobs completed in date range
     */
    public function scopeCompletedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('completed_at', [$startDate, $endDate]);
    }

    /**
     * Get completion statistics for a queue
     */
    public static function getStatsForQueue(string $queueName, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        
        return [
            'total' => static::forQueue($queueName)
                ->where('completed_at', '>=', $startDate)
                ->count(),
            'today' => static::forQueue($queueName)
                ->completedToday()
                ->count(),
            'average_per_day' => static::forQueue($queueName)
                ->where('completed_at', '>=', $startDate)
                ->count() / max($days, 1),
        ];
    }

    /**
     * Clean up old completed jobs
     */
    public static function cleanup(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return static::where('completed_at', '<', $cutoffDate)->delete();
    }
}