<?php

namespace Iquesters\Foundation\Http\Query;

use Iquesters\Foundation\Constants\EntityListParams;

/**
 * List query parameters shared by the entity and business entity APIs:
 * filters (?name[like]=john, ?status=active) and sort (?sort=name,-created_at).
 */
trait ParsesListQuery
{
    /** ?name[like]=john -> [['field' => 'name', 'op' => 'like', 'value' => 'john']]; ?status=active -> eq. */
    protected function parseListFilters(array $queryParams): array
    {
        $reserved = [
            EntityListParams::PARAM_OFFSET,
            EntityListParams::PARAM_LENGTH,
            EntityListParams::PARAM_SORT,
            EntityListParams::PARAM_FILTER,
            EntityListParams::PARAM_SEARCH,
        ];

        $filters = [];

        foreach ($queryParams as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $op => $opValue) {
                    if ($opValue !== null && $opValue !== '') {
                        $filters[] = ['field' => $key, 'op' => (string) $op, 'value' => $opValue];
                    }
                }
            } elseif ($value !== null && $value !== '') {
                $filters[] = ['field' => $key, 'op' => EntityListParams::OP_EQ, 'value' => $value];
            }
        }

        return $filters;
    }

    /** "name,-created_at" -> [['column' => 'name', 'direction' => 'asc'], ['column' => 'created_at', 'direction' => 'desc']]. */
    protected function parseListSorts(string $sortParam): array
    {
        $sorts = [];

        foreach (explode(',', $sortParam) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $sorts[] = str_starts_with($part, '-')
                ? ['column' => substr($part, 1), 'direction' => EntityListParams::DIR_DESC]
                : ['column' => $part, 'direction' => EntityListParams::DIR_ASC];
        }

        return $sorts;
    }
}
