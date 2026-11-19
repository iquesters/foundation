<?php

namespace Iquesters\Foundation\Services\Entity;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Helpers\DateTimeHelper;

/**
 * Shapes entity records for API responses: redacts sensitive fields and adds
 * a detail summary (last editor name + display time).
 */
class EntityPresenter
{
    public const SENSITIVE_FIELDS = [
        'password', 'remember_token', 'token', 'access_token', 'refresh_token', 'secret', 'api_key',
    ];

    public function many(iterable $records): Collection
    {
        $records = collect($records);

        if ($records->isEmpty()) {
            return $records;
        }

        $userNames = $this->userNames($records);

        return $records->map(function ($record) use ($userNames) {
            $record = $this->redact($record);
            $record->detail_summary = $this->summary($record, $userNames);

            return $record;
        });
    }

    public function one(object $record): object
    {
        return $this->many([$record])->first();
    }

    public function redact(object $record): object
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (property_exists($record, $field)) {
                unset($record->{$field});
            }
        }

        if (isset($record->meta) && is_iterable($record->meta)) {
            foreach ($record->meta as $metaItem) {
                if (isset($metaItem->meta_key) && $this->isSensitive((string) $metaItem->meta_key)) {
                    $metaItem->meta_value = '***REDACTED***';
                }
            }
        }

        return $record;
    }

    public function isSensitive(string $field): bool
    {
        $field = Str::lower($field);

        foreach (self::SENSITIVE_FIELDS as $sensitive) {
            if ($field === $sensitive || Str::contains($field, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function userNames(Collection $records): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $ids = $records
            ->flatMap(fn ($r) => [$r->updated_by ?? null, $r->created_by ?? null])
            ->filter(fn ($id) => ! is_null($id) && $id !== '')
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }

    private function summary(object $record, array $userNames): array
    {
        $userId = $record->updated_by ?? $record->created_by ?? null;
        $timestamp = $record->updated_at ?? $record->created_at ?? null;

        return [
            'user_name' => $userNames[$userId] ?? null,
            'display_datetime' => DateTimeHelper::displayDateTime($timestamp),
        ];
    }
}
